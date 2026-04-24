<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

final class ForgotPasswordController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer
    ) {
    }

    #[Route('/api/forgot-password', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true) ?? [];
        } else {
            $data = $request->request->all();
        }

        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            return new JsonResponse(['message' => 'El correo electrónico es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => 'El formato del correo no es válido.'], Response::HTTP_BAD_REQUEST);
        }

        // Siempre devolvemos la misma respuesta para no revelar si el email existe
        $genericMessage = 'Si existe una cuenta con ese correo, recibirás un enlace para recuperar tu contraseña.';

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)]);

        if ($user === null) {
            return new JsonResponse(['message' => $genericMessage], Response::HTTP_OK);
        }

        // Generamos el token de recuperación
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $this->entityManager->flush();

        // Creamos el enlace de recuperación
        $resetLink = 'http://localhost:4200/reset-password?token=' . $token;

        $emailMsg = (new Email())
            ->from('no-reply@taskhive.app')
            ->to($user->getEmail())
            ->subject('Recuperación de contraseña en TaskHive')
            ->text("Hola {$user->getName()},\n\nPara recuperar tu contraseña haz click en el siguiente enlace:\n{$resetLink}\n\nSi no has sido tú, ignora este mensaje.")
            ->html("<p>Hola {$user->getName()},</p><p>Para recuperar tu contraseña haz click en el siguiente enlace:</p><p><a href=\"{$resetLink}\">{$resetLink}</a></p><p>Si no has sido tú, ignora este mensaje.</p>");

        try {
            $this->mailer->send($emailMsg);
        } catch (\Exception) {
            // Si falla el envío, no se lo decimos al usuario
            return new JsonResponse(['message' => $genericMessage], Response::HTTP_OK);
        }

        return new JsonResponse(['message' => $genericMessage], Response::HTTP_OK);
    }
}
