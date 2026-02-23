<?php
/**
 * Simple Gmail SMTP Sender Class
 * For Windows XAMPP environments
 */
class GmailSMTP {
    private $host = 'smtp.gmail.com';
    private $port = 587;
    private $username;
    private $password;
    private $from;
    private $connection;
    
    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $this->from = $username;
    }
    
    public function send($to, $subject, $htmlContent) {
        try {
            // Connect to Gmail SMTP
            $this->connection = stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT
            );
            
            if (!$this->connection) {
                error_log("SMTP Connection Error: $errstr ($errno)");
                return false;
            }
            
            // Read initial response
            $this->readResponse();
            
            // Send EHLO
            $this->sendCommand("EHLO localhost");
            $this->readResponse();
            
            // Upgrade to TLS
            $this->sendCommand("STARTTLS");
            $this->readResponse();
            
            // Enable crypto
            if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("Failed to enable TLS encryption");
                return false;
            }
            
            // Send EHLO again after TLS
            $this->sendCommand("EHLO localhost");
            $this->readResponse();
            
            // Authenticate
            $this->sendCommand("AUTH LOGIN");
            $this->readResponse();
            
            $this->sendCommand(base64_encode($this->username));
            $this->readResponse();
            
            $this->sendCommand(base64_encode($this->password));
            $response = $this->readResponse();
            
            if (strpos($response, '235') === false) {
                error_log("Authentication failed");
                $this->disconnect();
                return false;
            }
            
            // Send mail
            $this->sendCommand("MAIL FROM:<{$this->from}>");
            $this->readResponse();
            
            $this->sendCommand("RCPT TO:<$to>");
            $this->readResponse();
            
            $this->sendCommand("DATA");
            $this->readResponse();
            
            // Build message
            $message = $this->buildMessage($to, $subject, $htmlContent);
            fwrite($this->connection, $message . "\r\n.\r\n");
            $this->readResponse();
            
            // Quit
            $this->sendCommand("QUIT");
            $this->disconnect();
            
            return true;
        } catch (Exception $e) {
            error_log("Gmail SMTP Error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }
    
    private function sendCommand($command) {
        fwrite($this->connection, $command . "\r\n");
    }
    
    private function readResponse() {
        $response = '';
        while ($line = fgets($this->connection, 1024)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
    
    private function buildMessage($to, $subject, $htmlContent) {
        $boundary = md5(time());
        
        $message = "From: {$this->from}\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n";
        $message .= "\r\n";
        $message .= $htmlContent;
        
        return $message;
    }
    
    private function disconnect() {
        if ($this->connection) {
            fclose($this->connection);
            $this->connection = null;
        }
    }
}
?>
