<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Functional\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Ecourty\TokenBundle\Event\TokenAccessDeniedEvent;
use Ecourty\TokenBundle\Repository\TokenRepository;
use Ecourty\TokenBundle\Service\TokenManager;
use Ecourty\TokenBundle\Tests\App\Entity\TestUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RequiresTokenListenerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private TokenManager $tokenManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $registry = static::getContainer()->get('doctrine');
        \assert($registry instanceof ManagerRegistry);
        $repository = new TokenRepository($registry);

        $this->tokenManager = new TokenManager($repository, $this->em, new \Symfony\Component\EventDispatcher\EventDispatcher(), 32);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    // --- Default resolver (HeaderTokenResolver — X-Token header) ---

    public function testProtectedRouteWithXTokenHeader(): void
    {
        $user = $this->createUser();
        $token = $this->tokenManager->create('access', $user, '+1 hour', false);

        $this->client->request('GET', '/protected', server: [
            'HTTP_X_TOKEN' => $token->getToken(),
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{status: string, token_type: string} $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('access', $data['token_type']);
    }

    public function testProtectedRouteWithoutToken(): void
    {
        $this->client->catchExceptions(false);

        $this->expectException(\Ecourty\TokenBundle\Exception\TokenAccessDeniedException::class);
        $this->client->request('GET', '/protected');
    }

    public function testProtectedRouteWithInvalidToken(): void
    {
        $this->client->catchExceptions(false);

        $this->expectException(\Ecourty\TokenBundle\Exception\TokenAccessDeniedException::class);
        $this->client->request('GET', '/protected', server: [
            'HTTP_X_TOKEN' => 'invalid-token',
        ]);
    }

    public function testProtectedRouteWithExpiredToken(): void
    {
        $user = $this->createUser();
        $token = $this->tokenManager->create('access', $user, '-1 second', false);

        $this->client->catchExceptions(false);

        $this->expectException(\Ecourty\TokenBundle\Exception\TokenAccessDeniedException::class);
        $this->client->request('GET', '/protected', server: [
            'HTTP_X_TOKEN' => $token->getToken(),
        ]);
    }

    public function testProtectedRouteWithRevokedToken(): void
    {
        $user = $this->createUser();
        $token = $this->tokenManager->create('access', $user, '+1 hour', false);
        $this->tokenManager->revoke($token->getToken());

        $this->client->catchExceptions(false);

        $this->expectException(\Ecourty\TokenBundle\Exception\TokenAccessDeniedException::class);
        $this->client->request('GET', '/protected', server: [
            'HTTP_X_TOKEN' => $token->getToken(),
        ]);
    }

    // --- QueryStringTokenResolver ---

    public function testQueryStringResolverWithValidToken(): void
    {
        $user = $this->createUser();
        $token = $this->tokenManager->create('access', $user, '+1 hour', false);

        $this->client->request('GET', '/query-string?token=' . $token->getToken());

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testQueryStringResolverWithoutToken(): void
    {
        $this->client->catchExceptions(false);

        $this->expectException(\Ecourty\TokenBundle\Exception\TokenAccessDeniedException::class);
        $this->client->request('GET', '/query-string');
    }

    // --- Custom resolver (X-Token header) ---

    public function testCustomResolverRoute(): void
    {
        $user = $this->createUser();
        $token = $this->tokenManager->create('access', $user, '+1 hour', false);

        $this->client->request('GET', '/custom-resolver', server: ['HTTP_X_API_KEY' => $token->getToken()]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testCustomResolverRouteMissingHeader(): void
    {
        $this->client->catchExceptions(false);

        $this->expectException(\Ecourty\TokenBundle\Exception\TokenAccessDeniedException::class);
        $this->client->request('GET', '/custom-resolver');
    }

    // --- Public route ---

    public function testPublicRouteNeedsNoToken(): void
    {
        $this->client->request('GET', '/public');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        /** @var array{status: string} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('public', $data['status']);
    }

    // --- Events ---

    public function testAccessDeniedEventIsDispatched(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcherInterface);

        $dispatched = false;
        $dispatcher->addListener(TokenAccessDeniedEvent::class, function (TokenAccessDeniedEvent $event) use (&$dispatched): void {
            $dispatched = true;
            $this->assertSame('access', $event->tokenType);
        });

        $this->client->catchExceptions(true);
        $this->client->request('GET', '/protected');

        $this->assertTrue($dispatched, 'TokenAccessDeniedEvent should have been dispatched');
    }

    public function testAccessDeniedEventCanOverrideResponse(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcherInterface);

        $dispatcher->addListener(TokenAccessDeniedEvent::class, function (TokenAccessDeniedEvent $event): void {
            $event->setResponse(new \Symfony\Component\HttpFoundation\JsonResponse(
                ['error' => 'Custom denied'],
                403,
            ));
        });

        $this->client->request('GET', '/protected');

        $response = $this->client->getResponse();
        $this->assertSame(403, $response->getStatusCode());

        /** @var array{error: string} $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('Custom denied', $data['error']);
    }

    private function createUser(): TestUser
    {
        $user = new TestUser('test@example.com');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
