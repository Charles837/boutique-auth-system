<?php
require_once __DIR__ . '/config.php';
clear_session_state();
set_flash('success', isset($_GET['cancel']) ? 'Sign-in cancelled. Please start again.' : 'You have signed out securely.');
header('Location: index.php');
exit;
