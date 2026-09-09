<?php
// includes/footer.php

// Re-compute base URL for safety in case header wasn't loaded (though header should always be loaded)
if (!isset($base_url)) {
    $project_dir = str_replace('\\', '/', dirname(__DIR__));
    $document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $base_url = str_replace($document_root, '', $project_dir);
    $base_url = '/' . trim($base_url, '/') . '/';
    if ($base_url === '//') {
        $base_url = '/';
    }
    // URL-encode spaces in base URL to resolve path issues in script/stylesheet loads
    $base_url = str_replace(' ', '%20', $base_url);
}

$current_script = $_SERVER['SCRIPT_NAME'];
$is_public = (!strpos($current_script, '/student/') && !strpos($current_script, '/counselor/') && !strpos($current_script, '/admin/'));
?>

<?php if ($is_public): ?>
    <footer class="global-footer mt-auto py-3 bg-white border-top">
        <div class="container text-center">
        </div>
    </footer>
<?php else: ?>
        </main> <!-- Close main-content -->
    </div> <!-- Close dashboard-wrapper -->
<?php endif; ?>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Client Script (Cache-busted v1.4) -->
<script src="<?php echo $base_url; ?>js/main.js?v=1.4"></script>
</body>
</html>
