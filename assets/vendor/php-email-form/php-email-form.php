<?php

class PHP_Email_Form
{
    public $to = '';
    public $from_name = '';
    public $from_email = '';
    public $subject = '';
    public $message = '';
    public $smtp = array();
    public $ajax = false;

    private $messages = array();

    public function add_message($content, $label, $check_len = 0)
    {
        if ($check_len > 0 && strlen($content) < $check_len) {
            // Validation could happen here, but we will accept for now
        }
        $this->messages[] = array('label' => $label, 'content' => $content);
    }

    public function send()
    {
        $this->to = filter_var($this->to, FILTER_SANITIZE_EMAIL);
        $this->from_email = filter_var($this->from_email, FILTER_SANITIZE_EMAIL);

        // Build email body
        $email_content = "<html><body>";
        $email_content .= "<h2>" . htmlspecialchars($this->subject) . "</h2>";
        foreach ($this->messages as $msg) {
            $email_content .= "<p><strong>" . htmlspecialchars($msg['label']) . ":</strong><br>" . nl2br(htmlspecialchars($msg['content'])) . "</p>";
        }
        $email_content .= "</body></html>";

        // Use SMTP if configured
        if (!empty($this->smtp) && !empty($this->smtp['host'])) {
            return $this->sendSMTP($email_content);
        }

        // Fallback to mail()
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">" . "\r\n";
        $headers .= "Reply-To: " . $this->from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if (mail($this->to, $this->subject, $email_content, $headers)) {
            return 'OK';
        } else {
            return 'Unable to send email. please verify your PHP configuration or SMTP settings.';
        }
    }

    private function sendSMTP($content)
    {
        $host = $this->smtp['host'];
        $port = isset($this->smtp['port']) ? $this->smtp['port'] : 25;
        $username = isset($this->smtp['username']) ? $this->smtp['username'] : '';
        $password = isset($this->smtp['password']) ? $this->smtp['password'] : '';

        // Determine info for handshake
        $localhost = $_SERVER['SERVER_NAME'];

        // Connect
        $protocol = '';
        if ($port == 465) $protocol = 'ssl://';

        $socket = fsockopen($protocol . $host, $port, $errno, $errstr, 15);
        if (!$socket) {
            return "Error: Could not connect to SMTP host. $errno $errstr";
        }

        $this->read_response($socket);

        // HELO/EHLO
        if (!$this->send_cmd($socket, "EHLO $localhost")) {
            if (!$this->send_cmd($socket, "HELO $localhost")) {
                return "Error: HELO/EHLO failed";
            }
        }

        // STARTTLS
        if ($port == 587) {
            if ($this->send_cmd($socket, "STARTTLS")) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->send_cmd($socket, "EHLO $localhost");
            }
        }

        // Auth
        if (!empty($username) && !empty($password)) {
            if (!$this->send_cmd($socket, "AUTH LOGIN")) return "Error: AUTH LOGIN failed";
            if (!$this->send_cmd($socket, base64_encode($username))) return "Error: Username failed";
            if (!$this->send_cmd($socket, base64_encode($password))) return "Error: Password failed";
        }

        // Mail Transaction
        if (!$this->send_cmd($socket, "MAIL FROM: <" . $this->from_email . ">")) return "Error: MAIL FROM failed";
        if (!$this->send_cmd($socket, "RCPT TO: <" . $this->to . ">")) return "Error: RCPT TO failed";
        if (!$this->send_cmd($socket, "DATA")) return "Error: DATA failed";

        // Headers & Body
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">\r\n";
        $headers .= "To: " . $this->to . "\r\n";
        $headers .= "Subject: " . $this->subject . "\r\n";
        $headers .= "Reply-To: " . $this->from_email . "\r\n";

        fputs($socket, $headers . "\r\n" . $content . "\r\n.\r\n");

        $result = $this->read_response($socket);
        $code = substr($result, 0, 3);

        $this->send_cmd($socket, "QUIT");
        fclose($socket);

        if ($code == '250') {
            return 'OK';
        } else {
            return "Error: Email sending failed. " . $result;
        }
    }

    private function read_response($socket)
    {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }

    private function send_cmd($socket, $cmd)
    {
        fputs($socket, $cmd . "\r\n");
        $response = $this->read_response($socket);
        $code = substr($response, 0, 3);
        return ($code >= 200 && $code < 400);
    }
}
