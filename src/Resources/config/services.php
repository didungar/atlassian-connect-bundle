<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AtlassianConnectBundle\Command\RequestAPICommand;
use AtlassianConnectBundle\Controller\DescriptorController;
use AtlassianConnectBundle\Controller\HandshakeController;
use AtlassianConnectBundle\Controller\UnlicensedController;
use AtlassianConnectBundle\Entity\Tenant;
use AtlassianConnectBundle\Listener\LicenseListener;
use AtlassianConnectBundle\Repository\TenantRepository;
use AtlassianConnectBundle\Repository\TenantRepositoryInterface;
use AtlassianConnectBundle\Security\JWTAuthenticator;
use AtlassianConnectBundle\Security\JWTSecurityHelper;
use AtlassianConnectBundle\Security\JWTSecurityHelperInterface;
use AtlassianConnectBundle\Security\JWTUserProvider;
use AtlassianConnectBundle\Service\AtlassianRestClient;
use AtlassianConnectBundle\Service\AtlassianRestClientFactory;
use AtlassianConnectBundle\Service\AtlassianRestClientInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('atlassian_connect_license_listener_class', LicenseListener::class)
        ->set('atlassian_connect_jwt_user_provider_class', JWTUserProvider::class)
        ->set('atlassian_connect_jwt_authenticator_class', JWTAuthenticator::class)
        ->set('atlassian_connect_tenant_entity_class', Tenant::class);

    $container->services()
        ->set(UnlicensedController::class)
            ->args([service(Environment::class)])
            ->tag('controller.service_arguments')
        ->set(HandshakeController::class)
            ->args([
                service(TenantRepositoryInterface::class),
                service(LoggerInterface::class),
            ])
            ->tag('controller.service_arguments')
        ->set(DescriptorController::class)
            ->args([param('atlassian_connect')])
            ->tag('controller.service_arguments')
        ->set(RequestAPICommand::class)
            ->args([
                service(TenantRepositoryInterface::class),
                service('atlassian_connect_rest_client'),
            ])
            ->tag('console.command')
        ->set('atlassian_connect_rest_client', AtlassianRestClient::class)
            ->public()
            ->factory([AtlassianRestClientFactory::class, 'createAtlassianRestClient'])
            ->args([service(TokenStorageInterface::class)])
        ->alias(AtlassianRestClientInterface::class, 'atlassian_connect_rest_client')
            ->public()
        ->set(JWTSecurityHelper::class)
            ->args([
                service(TenantRepositoryInterface::class),
                param('atlassian_connect_dev_tenant'),
                param('kernel.environment'),
            ])
        ->alias(JWTSecurityHelperInterface::class, JWTSecurityHelper::class)
        ->set('kernel.listener.license_listener', '%atlassian_connect_license_listener_class%')
            ->args([
                service(RouterInterface::class),
                service(TokenStorageInterface::class),
                param('kernel.environment'),
                param('atlassian_connect_license_allow_list'),
            ])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest'])
        ->set('jwt_user_provider', '%atlassian_connect_jwt_user_provider_class%')
            ->args([
                service(TenantRepositoryInterface::class),
            ])
        ->set('jwt_authenticator', '%atlassian_connect_jwt_authenticator_class%')
            ->args([
                service('jwt_user_provider'),
                service(JWTSecurityHelperInterface::class),
            ])
        ->alias(JWTAuthenticator::class, (service('jwt_authenticator'))->__toString())
        ->set(TenantRepositoryInterface::class, TenantRepository::class)
            ->args([
                service(ManagerRegistry::class),
                param('atlassian_connect_tenant_entity_class'),
            ])
    ;
};
