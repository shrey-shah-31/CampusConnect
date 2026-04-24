<?php
function send_portal_mail(string $to, string $subject, string $body): bool {
    // Replace with PHPMailer integration when configured.
    return @mail($to, $subject, $body);
}
