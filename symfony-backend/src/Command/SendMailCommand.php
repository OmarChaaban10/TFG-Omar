<?php
# src/Command/SendMailCommand.php
# php bin/console app:send-mail

namespace App\Command;

use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Mime\Address;

#[AsCommand(name: 'app:send-mail')]
final class SendMailCommand
{
    public function __invoke(): int {
        $email = (new MailtrapEmail())
            ->from(new Address('hello@demomailtrap.co', 'Mailtrap Test'))
            ->to(new Address('omarmounder.s454046@cesurformacion.com'))
            ->subject('You are awesome!')
            ->category('Integration Test')
            ->text('Congrats for sending test email with Mailtrap!')
        ;

        $response = MailtrapClient::initSendingEmails(
            apiKey: '81b9f2b6c29b4dc63b5449ccb831d88f'
        )->send($email);

        var_dump(ResponseHelper::toArray($response));

        return Command::SUCCESS;
    }
}