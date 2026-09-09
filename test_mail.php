<?php
// test_mail.php
$page_title = "Mail Server Diagnostics & Tester";
require_once 'includes/auth.php';

// Load default settings
$config_file = __DIR__ . '/config/mail_settings.php';
$saved_config = [];
if (file_exists($config_file)) {
    $saved_config = require $config_file;
}

$error = '';
$success = '';
$debug_log = '';
$test_results = null;

// Determine if config has placeholder values
$has_placeholders = false;
if (empty($saved_config['smtp_username']) || empty($saved_config['smtp_password']) || 
    $saved_config['smtp_username'] === 'your_gmail@gmail.com' || 
    $saved_config['smtp_password'] === 'your_google_app_password') {
    $has_placeholders = true;
}

// 1. Perform Network & Port Diagnostic Checks
$smtp_host = $_POST['host'] ?? $saved_config['smtp_host'] ?? 'smtp.gmail.com';
$ports_to_check = [25, 465, 587];
$port_diagnostics = [];
foreach ($ports_to_check as $port) {
    // 2-second timeout for quick feedback
    $connection = @fsockopen($smtp_host, $port, $errno, $errstr, 2);
    if (is_resource($connection)) {
        $port_diagnostics[$port] = [
            'status' => 'Open',
            'class' => 'success',
            'icon' => 'check-circle-fill'
        ];
        fclose($connection);
    } else {
        $port_diagnostics[$port] = [
            'status' => 'Blocked / Timeout (' . $errno . ': ' . $errstr . ')',
            'class' => 'danger',
            'icon' => 'x-circle-fill'
        ];
    }
}

// 2. Handle Test Email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_send') {
    $to = filter_var(trim($_POST['recipient'] ?? ''), FILTER_SANITIZE_EMAIL);
    
    // Determine source of SMTP configs (saved config vs temporary inputs)
    $use_custom = isset($_POST['use_custom']);
    $host = trim($_POST['host'] ?? '');
    $port = intval($_POST['port'] ?? 587);
    $secure = $_POST['secure'] ?? 'tls';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $from_email = trim($_POST['from_email'] ?? 'no-reply@mindcare.edu');
    $from_name = trim($_POST['from_name'] ?? 'MindCare Hub Test');

    if (empty($to)) {
        $error = 'Please enter a recipient email address.';
    } else {
        // Explicitly load PHPMailer files
        require_once __DIR__ . '/includes/PHPMailer/Exception.php';
        require_once __DIR__ . '/includes/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/includes/PHPMailer/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $debug_log = '';

        try {
            // Setup custom debug logger output
            $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str, $level) use (&$debug_log) {
                $debug_log .= htmlspecialchars($str) . "\n";
            };

            // Server Settings
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;

            // SSL bypass options (helps local certificate mismatches)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Recipients
            $mail->setFrom($username, $from_name);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test Connection - MindCare Hub Diagnostics';
            $mail->Body    = '<h3>SMTP Test Success!</h3>' .
                             '<p>If you are reading this email, your SMTP configurations are completely functional.</p>' .
                             '<p>Sent at: <strong>' . date('Y-m-d H:i:s') . '</strong></p>';
            $mail->AltBody = 'SMTP Test Success! If you are reading this, your configurations are functional. Sent at: ' . date('Y-m-d H:i:s');

            $mail->send();
            $success = 'Test email successfully sent to ' . htmlspecialchars($to) . '!';
            $test_results = true;
        } catch (Exception $e) {
            $error = 'Email failed to send. See verbose debug console below.';
            $test_results = false;
        }
    }
}

// 3. Read local logs/emails.log if it exists
$log_contents = '';
$log_file = __DIR__ . '/logs/emails.log';
if (file_exists($log_file)) {
    // Read the last 4000 bytes for logs view to avoid overloading page
    $handle = fopen($log_file, "r");
    if ($handle) {
        $size = filesize($log_file);
        $read_len = min(4000, $size);
        if ($read_len > 0) {
            fseek($handle, -$read_len, SEEK_END);
            $log_contents = fread($handle, $read_len);
        }
        fclose($handle);
    }
}

require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <!-- Top warning header -->
        <div class="col-12 mb-4">
            <div class="alert alert-warning alert-custom border border-warning shadow-sm d-flex align-items-center gap-3" role="alert">
                <span class="fs-2 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></span>
                <div>
                    <h5 class="fw-bold mb-1">Developer Diagnostic Page</h5>
                    <p class="mb-0 small text-muted">This script is designed for local SMTP connection testing. <strong>IMPORTANT:</strong> Delete or disable this file (<code>test_mail.php</code>) before deploying the application to production.</p>
                </div>
            </div>
        </div>

        <!-- Left Column: Diagnostic checks & Logs -->
        <div class="col-lg-6 mb-4">
            <!-- SMTP Configuration Info -->
            <div class="card card-glass shadow border-0 p-4 mb-4">
                <h4 class="fw-bold mb-3 text-primary-custom d-flex align-items-center gap-2">
                    <i class="bi bi-gear-fill"></i> Saved SMTP Settings
                </h4>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless align-middle mb-0 text-muted">
                        <tbody>
                            <tr>
                                <th class="w-40 text-dark fw-semibold">SMTP Host:</th>
                                <td><code><?php echo h($saved_config['smtp_host'] ?? 'Not Configured'); ?></code></td>
                            </tr>
                            <tr>
                                <th class="text-dark fw-semibold">SMTP Port:</th>
                                <td><code><?php echo h($saved_config['smtp_port'] ?? 'Not Configured'); ?></code> (Security: <code><?php echo h($saved_config['smtp_secure'] ?? 'None'); ?></code>)</td>
                            </tr>
                            <tr>
                                <th class="text-dark fw-semibold">SMTP Username:</th>
                                <td>
                                    <?php if ($has_placeholders): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-exclamation-circle-fill"></i> Placeholders Used</span>
                                    <?php else: ?>
                                        <code><?php echo h($saved_config['smtp_username']); ?></code>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-dark fw-semibold">From Name & Email:</th>
                                <td>"<?php echo h($saved_config['from_name'] ?? 'MindCare Hub'); ?>" &lt;<code><?php echo h($saved_config['from_email'] ?? 'no-reply@mindcare.edu'); ?></code>&gt;</td>
                            </tr>
                            <tr>
                                <th class="text-dark fw-semibold">SMTP Status:</th>
                                <td>
                                    <?php if ($has_placeholders): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Inactive (Local mail() Fallback)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active (SMTP PHPMailer Enabled)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Network Connectivity Tests -->
            <div class="card card-glass shadow border-0 p-4 mb-4">
                <h4 class="fw-bold mb-3 text-primary-custom d-flex align-items-center gap-2">
                    <i class="bi bi-activity"></i> Network Port Diagnostics
                </h4>
                <p class="small text-muted mb-3">Checking outbound SMTP TCP connections to <code><?php echo h($smtp_host); ?></code>. Firewalls, network security, or local ISPs frequently block ports 587/465.</p>
                
                <div class="list-group list-group-flush border-top border-bottom">
                    <?php foreach ($port_diagnostics as $port => $info): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-0 bg-transparent">
                            <div>
                                <span class="fw-bold text-dark">Port <?php echo $port; ?></span>
                                <span class="small text-muted ms-2">
                                    <?php 
                                        if ($port == 587) echo '(TLS Recommended)';
                                        elseif ($port == 465) echo '(SSL Encryption)';
                                        elseif ($port == 25) echo '(Standard Plaintext)';
                                    ?>
                                </span>
                            </div>
                            <span class="badge bg-<?php echo $info['class']; ?>-subtle text-<?php echo $info['class']; ?> border border-<?php echo $info['class']; ?>-subtle d-flex align-items-center gap-1.5 px-2.5 py-1.5">
                                <i class="bi bi-<?php echo $info['icon']; ?>"></i> <?php echo $info['status']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Log Viewer for emails.log -->
            <div class="card card-glass shadow border-0 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0 text-primary-custom d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text-fill"></i> Local Mail Logs
                    </h4>
                    <span class="small text-muted"><code>logs/emails.log</code></span>
                </div>
                <p class="small text-muted mb-3">Below is the tail end of your local email files log. Perfect for copying verification codes locally.</p>
                <?php if (empty($log_contents)): ?>
                    <div class="alert alert-secondary text-center small py-3" role="alert">
                        No logs recorded yet or file is empty.
                    </div>
                <?php else: ?>
                    <pre class="bg-dark text-white p-3 rounded small font-monospace overflow-y-auto mb-0" style="max-height: 280px; font-size: 11.5px;"><?php echo htmlspecialchars($log_contents); ?></pre>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Tester Form & Console Output -->
        <div class="col-lg-6 mb-4">
            <!-- Testing Form -->
            <div class="card card-glass shadow border-0 p-4 mb-4">
                <h4 class="fw-bold mb-3 text-primary-custom d-flex align-items-center gap-2">
                    <i class="bi bi-envelope-check-fill"></i> Send Test Email
                </h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-custom mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-custom mb-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo h($success); ?>
                    </div>
                <?php endif; ?>

                <form action="test_mail.php" method="POST">
                    <input type="hidden" name="action" value="test_send">

                    <div class="mb-3">
                        <label for="recipient" class="form-label text-dark fw-semibold">Recipient Email Address</label>
                        <input type="email" class="form-control" name="recipient" id="recipient" placeholder="receiver@example.com" value="<?php echo isset($_POST['recipient']) ? h($_POST['recipient']) : ''; ?>" required>
                        <div class="form-text">The email address to send the test message to.</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="useCustomCheck" name="use_custom" <?php echo isset($_POST['use_custom']) ? 'checked' : ''; ?> onchange="toggleCustomSMTP()">
                            <label class="form-check-label text-dark fw-semibold" for="useCustomCheck">Modify SMTP Settings for Test</label>
                        </div>
                        <div class="form-text">Toggle this to input temporary SMTP settings for debugging without altering the config file.</div>
                    </div>

                    <div id="smtpFields" style="<?php echo isset($_POST['use_custom']) ? '' : 'display: none;'; ?>">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="host" class="form-label text-dark small fw-semibold">SMTP Host</label>
                                <input type="text" class="form-control form-control-sm" name="host" id="host" value="<?php echo h($_POST['host'] ?? $saved_config['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="port" class="form-label text-dark small fw-semibold">Port</label>
                                <input type="number" class="form-control form-control-sm" name="port" id="port" value="<?php echo h($_POST['port'] ?? $saved_config['smtp_port'] ?? 587); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="secure" class="form-label text-dark small fw-semibold">Encryption</label>
                                <select class="form-select form-select-sm" name="secure" id="secure">
                                    <option value="tls" <?php echo ($_POST['secure'] ?? $saved_config['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
                                    <option value="ssl" <?php echo ($_POST['secure'] ?? $saved_config['smtp_secure'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL (SMTPS)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="from_name" class="form-label text-dark small fw-semibold">Sender Display Name</label>
                                <input type="text" class="form-control form-control-sm" name="from_name" id="from_name" value="<?php echo h($_POST['from_name'] ?? $saved_config['from_name'] ?? 'MindCare Test'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label text-dark small fw-semibold">SMTP Username / Email</label>
                            <input type="text" class="form-control form-control-sm" name="username" id="username" placeholder="user@gmail.com" value="<?php echo h($_POST['username'] ?? $saved_config['smtp_username'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label text-dark small fw-semibold">SMTP Password / Google App Password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control form-control-sm border-end-0" name="password" id="password" placeholder="App Password" value="<?php echo h($_POST['password'] ?? $saved_config['smtp_password'] ?? ''); ?>">
                                <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2.5 mt-2">
                        <i class="bi bi-send-fill me-2"></i> Execute SMTP Mail Test
                    </button>
                </form>
            </div>

            <!-- PHPMailer Verbose Console Output -->
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_send'): ?>
                <div class="card card-glass shadow border-0 p-4">
                    <h4 class="fw-bold mb-3 text-primary-custom d-flex align-items-center gap-2">
                        <i class="bi bi-terminal-fill"></i> SMTP Verbose Handshake Log
                    </h4>
                    <p class="small text-muted mb-3">Review the full transaction logs below to spot certificate errors, auth failure, or host connect timeouts.</p>
                    
                    <?php if ($test_results === true): ?>
                        <div class="alert alert-success py-2 small mb-3">
                            <i class="bi bi-check-circle-fill me-1"></i> Transaction completed successfully.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger py-2 small mb-3">
                            <i class="bi bi-x-circle-fill me-1"></i> Transaction failed. Analyze debug stream below.
                        </div>
                    <?php endif; ?>

                    <pre class="bg-dark text-success p-3 rounded small font-monospace overflow-y-auto mb-0" style="max-height: 380px; font-size: 11px; white-space: pre-wrap; word-break: break-all;"><?php echo $debug_log ?: 'No logs outputted by PHPMailer. Connection failed before SMTP sequence started.'; ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCustomSMTP() {
    const isChecked = document.getElementById('useCustomCheck').checked;
    const fieldsDiv = document.getElementById('smtpFields');
    
    if (isChecked) {
        fieldsDiv.style.display = 'block';
    } else {
        fieldsDiv.style.display = 'none';
        // Reset values to PHP configurations
        document.getElementById('host').value = "<?php echo h($saved_config['smtp_host'] ?? 'smtp.gmail.com'); ?>";
        document.getElementById('port').value = "<?php echo h($saved_config['smtp_port'] ?? 587); ?>";
        document.getElementById('secure').value = "<?php echo h($saved_config['smtp_secure'] ?? 'tls'); ?>";
        document.getElementById('username').value = "<?php echo h($saved_config['smtp_username'] ?? ''); ?>";
        document.getElementById('password').value = "<?php echo h($saved_config['smtp_password'] ?? ''); ?>";
        document.getElementById('from_name').value = "<?php echo h($saved_config['from_name'] ?? 'MindCare Hub Test'); ?>";
    }
}

// Keep inputs synced if custom option is selected but server validation occurred
document.addEventListener("DOMContentLoaded", function() {
    const check = document.getElementById('useCustomCheck');
    const fieldsDiv = document.getElementById('smtpFields');
    
    if (check.checked) {
        fieldsDiv.style.display = 'block';
    } else {
        // Sync elements on first load with current saved config if custom SMTP switch is off
        toggleCustomSMTP();
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>
