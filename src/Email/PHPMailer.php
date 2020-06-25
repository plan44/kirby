<?php

namespace Kirby\Email;

use PHPMailer\PHPMailer\PHPMailer as Mailer;

/**
 * Wrapper for PHPMailer library
 *
 * @package   Kirby Email
 * @author    Bastian Allgeier <bastian@getkirby.com>,
 *            Nico Hoffmann <nico@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier GmbH
 * @license   https://opensource.org/licenses/MIT
 */
class PHPMailer extends Email
{
    public function send(bool $debug = false): bool
    {
        $mailer = new Mailer(true);
        
        // set sender's address
        $mailer->setFrom($this->from(), $this->fromName() ?? '');

        // optional reply-to address
        if ($replyTo = $this->replyTo()) {
            $mailer->addReplyTo($replyTo, $this->replyToName() ?? '');
        }

        // add (multiple) recepient, CC & BCC addresses
        foreach ($this->to() as $email => $name) {
            $mailer->addAddress($email, $name ?? '');
        }
        foreach ($this->cc() as $email => $name) {
            $mailer->addCC($email, $name ?? '');
        }
        foreach ($this->bcc() as $email => $name) {
            $mailer->addBCC($email, $name ?? '');
        }

        $mailer->Subject = $this->subject();
        $mailer->CharSet = 'UTF-8';

        // set body according to html/text
        if ($this->isHtml()) {
            $mailer->isHTML(true);
            $mailer->Body = $this->body()->html();
            $mailer->AltBody = $this->body()->text();
        } else {
            $mailer->Body = $this->body()->text();
        }

        // add attachments
        foreach ($this->attachments() as $attachment) {
            $mailer->addAttachment($attachment);
        }

        // smtp transport settings
        if (($this->transport()['type'] ?? 'mail') === 'smtp') {
            $mailer->isSMTP();
            $mailer->Host       = $this->transport()['host'] ?? null;
            $mailer->SMTPAuth   = $this->transport()['auth'] ?? false;
            $mailer->Username   = $this->transport()['username'] ?? null;
            $mailer->Password   = $this->transport()['password'] ?? null;
            $mailer->SMTPSecure = $this->transport()['security'] ?? 'ssl';
            $mailer->Port       = $this->transport()['port'] ?? null;
        }

        if ($debug === true) {
            return $this->isSent = true;
        }

        //return $this->isSent = $mailer->send();
        $this->isSent = $mailer->send();
        $log = fopen('/home/gleistld/public_html/g70applogs/phpmailer.log', 'a');
        fwrite($log, "\n\n============ raw mail sent (type=" . ($this->transport()['type'] ?? 'mail') . ") " . strftime("%Y-%m-%d %H:%M") . "===========\n\n");
        fwrite($log, $mailer->getSentMIMEMessage());
        fwrite($log, "\n\n============ mailer = " . var_export($mailer, true) . "\n==============\n");
        fclose($log);
        return $this->isSent;
    }
}
