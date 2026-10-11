const express = require("express");
const sqlite3 = require("sqlite3").verbose();
const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");
const speakeasy = require("speakeasy");
const qrcode = require("qrcode");
const path = require("path");

const app = express();
const PORT = process.env.PORT || 3000;
const JWT_SECRET = process.env.JWT_SECRET || "boutique-secret-key";

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use("/public", express.static(path.join(__dirname, "public")));

const db = new sqlite3.Database(path.join(__dirname, "boutique.db"));

db.serialize(() => {
  db.run(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT UNIQUE NOT NULL,
      password TEXT NOT NULL,
      role TEXT NOT NULL CHECK(role IN ('Admin', 'Manager', 'Customer')),
      twoFactorSecret TEXT,
      isTwoFactorEnabled INTEGER DEFAULT 0
    )
  `);

  db.get("SELECT COUNT(*) AS count FROM users", (err, row) => {
    if (err) return;

    if (row.count === 0) {
      const sampleUsers = [
        ["Ava Collins", "admin@boutique.com", "admin123", "Admin"],
        ["Noah Reed", "manager@boutique.com", "manager123", "Manager"],
        ["Emma Stone", "customer@boutique.com", "customer123", "Customer"]
      ];

      sampleUsers.forEach(([name, email, password, role]) => {
        bcrypt.hash(password, 10).then((hashedPassword) => {
          db.run(
            "INSERT INTO users (name, email, password, role, twoFactorSecret, isTwoFactorEnabled) VALUES (?, ?, ?, ?, ?, ?)",
            [name, email, hashedPassword, role, null, 0]
          );
        });
      });
    }
  });
});

function generateToken(user) {
  return jwt.sign(
    { id: user.id, email: user.email, role: user.role },
    JWT_SECRET,
    { expiresIn: "1h" }
  );
}

function authMiddleware(req, res, next) {
  const authorizationHeader = req.headers.authorization;
  if (!authorizationHeader) {
    return res.status(401).json({ message: "Authorization header missing" });
  }

  const token = authorizationHeader.split(" ")[1];
  if (!token) {
    return res.status(401).json({ message: "Token missing" });
  }

  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    req.user = decoded;
    next();
  } catch (error) {
    return res.status(401).json({ message: "Invalid or expired token" });
  }
}

function requireRole(...allowedRoles) {
  return (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ message: "Unauthorized" });
    }

    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ message: "Access denied for this role" });
    }

    next();
  };
}

app.get("/", (req, res) => {
  res.sendFile(path.join(__dirname, "views", "login.html"));
});

app.get("/dashboard", authMiddleware, (req, res) => {
  res.sendFile(path.join(__dirname, "views", "dashboard.html"));
});

app.post("/api/register", async (req, res) => {
  const { name, email, password, role = "Customer" } = req.body;

  if (!name || !email || !password) {
    return res.status(400).json({ message: "Name, email, and password are required" });
  }

  if (!['Admin', 'Manager', 'Customer'].includes(role)) {
    return res.status(400).json({ message: "Invalid role selected" });
  }

  const hashedPassword = await bcrypt.hash(password, 10);

  db.run(
    "INSERT INTO users (name, email, password, role, twoFactorSecret, isTwoFactorEnabled) VALUES (?, ?, ?, ?, ?, ?)",
    [name, email, hashedPassword, role, null, 0],
    function (err) {
      if (err) {
        return res.status(400).json({ message: "User already exists or registration failed" });
      }

      res.status(201).json({ message: "Registration successful. Please log in." });
    }
  );
});

app.post("/api/login", (req, res) => {
  const { email, password } = req.body;

  if (!email || !password) {
    return res.status(400).json({ message: "Email and password are required" });
  }

  db.get("SELECT * FROM users WHERE email = ?", [email], async (err, user) => {
    if (err || !user) {
      return res.status(401).json({ message: "Invalid email or password" });
    }

    const isPasswordValid = await bcrypt.compare(password, user.password);
    if (!isPasswordValid) {
      return res.status(401).json({ message: "Invalid email or password" });
    }

    if (user.isTwoFactorEnabled === 1) {
      return res.json({
        message: "2FA required",
        requiresTwoFactor: true,
        userId: user.id
      });
    }

    const token = generateToken(user);
    return res.json({
      message: "Login successful",
      token,
      user: { id: user.id, name: user.name, email: user.email, role: user.role }
    });
  });
});

app.post("/api/setup-2fa", authMiddleware, (req, res) => {
  const secret = speakeasy.generateSecret({
    name: `Boutique-${req.user.email}`
  });

  db.run(
    "UPDATE users SET twoFactorSecret = ? WHERE email = ?",
    [secret.base32, req.user.email],
    (err) => {
      if (err) {
        return res.status(500).json({ message: "Unable to set up 2FA" });
      }

      qrcode.toDataURL(secret.otpauth_url, (qrErr, qrCode) => {
        if (qrErr) {
          return res.status(500).json({ message: "Unable to generate QR code" });
        }

        res.json({
          message: "Scan this QR code with your authenticator app",
          qrCode,
          secret: secret.base32
        });
      });
    }
  );
});

app.post("/api/verify-2fa", (req, res) => {
  const { userId, otp } = req.body;

  if (!userId || !otp) {
    return res.status(400).json({ message: "User ID and OTP are required" });
  }

  db.get("SELECT * FROM users WHERE id = ?", [userId], (err, user) => {
    if (err || !user) {
      return res.status(404).json({ message: "User not found" });
    }

    if (!user.twoFactorSecret) {
      return res.status(400).json({ message: "2FA is not configured for this account" });
    }

    const isValidOTP = speakeasy.totp.verify({
      secret: user.twoFactorSecret,
      encoding: "base32",
      token: otp
    });

    if (!isValidOTP) {
      return res.status(401).json({ message: "Invalid OTP code" });
    }

    db.run(
      "UPDATE users SET isTwoFactorEnabled = 1 WHERE id = ?",
      [userId],
      (updateErr) => {
        if (updateErr) {
          return res.status(500).json({ message: "Unable to enable 2FA" });
        }

        const token = generateToken(user);
        return res.json({
          message: "2FA verified successfully",
          token,
          user: { id: user.id, name: user.name, email: user.email, role: user.role }
        });
      }
    );
  });
});

app.get("/api/me", authMiddleware, (req, res) => {
  db.get("SELECT id, name, email, role, isTwoFactorEnabled FROM users WHERE email = ?", [req.user.email], (err, user) => {
    if (err || !user) {
      return res.status(404).json({ message: "User not found" });
    }

    return res.json({ user });
  });
});

app.get("/api/admin", authMiddleware, requireRole("Admin"), (req, res) => {
  res.json({ message: "Welcome to the admin panel", role: req.user.role });
});

app.get("/api/manager", authMiddleware, requireRole("Manager"), (req, res) => {
  res.json({ message: "Welcome to the manager dashboard", role: req.user.role });
});

app.get("/api/customer", authMiddleware, requireRole("Customer"), (req, res) => {
  res.json({ message: "Welcome to the customer portal", role: req.user.role });
});

app.listen(PORT, () => {
  console.log(`Boutique auth app is running on http://localhost:${PORT}`);
});
