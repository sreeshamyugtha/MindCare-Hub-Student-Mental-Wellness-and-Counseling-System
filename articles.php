<?php
// student/articles.php
$page_title = "Articles";
$active_nav = "articles";
require_once '../includes/auth.php';

// Assert Student Permissions
require_role('student');

require_once '../includes/header.php';
require_once '../includes/articles_content.php';
require_once '../includes/footer.php';
?>
