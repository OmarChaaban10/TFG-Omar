<?php

declare(strict_types=1);

namespace App\Command;

use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:send-mail',
    description: 'Send a test email via Mailtrap.',
)]
final class SendMailCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiKey = $_ENV['MAILTRAP_API_KEY'] ?? '';
        if ($apiKey === '') {
            $io->error('La variable de entorno MAILTRAP_API_KEY no está configurada.');

            return Command::FAILURE;
        }

        $email = (new MailtrapEmail())
            ->from(new Address('hello@demomailtrap.co', 'Mailtrap Test'))
            ->to(new Address('omarmounder.s454046@cesurformacion.com'))
            ->subject('You are awesome!')
            ->category('Integration Test')
            ->text('Congrats for sending test email with Mailtrap!');

        $response = MailtrapClient::initSendingEmails(
            apiKey: $apiKey,
        )->send($email);

        $io->success('Correo enviado correctamente.');
        $io->writeln(json_encode(ResponseHelper::toArray($response), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}