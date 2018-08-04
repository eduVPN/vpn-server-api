<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

use DateTime;
use SURFnet\VPN\Common\Http\ApiErrorResponse;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\RandomInterface;
use SURFnet\VPN\Server\CA\CaInterface;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\TlsAuth;

class CertificatesModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\CA\CaInterface */
    private $ca;

    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    /** @var \SURFnet\VPN\Server\TlsAuth */
    private $tlsAuth;

    /** @var \SURFnet\VPN\Common\RandomInterface */
    private $random;

    public function __construct(CaInterface $ca, Storage $storage, TlsAuth $tlsAuth, RandomInterface $random)
    {
        $this->ca = $ca;
        $this->storage = $storage;
        $this->tlsAuth = $tlsAuth;
        $this->random = $random;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        /* CERTIFICATES */
        $service->post(
            '/add_client_certificate',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $displayName = InputValidation::displayName($request->getPostParameter('display_name'));

                // generate a random string as the certificate's CN
                $commonName = $this->random->get(16);
                $certInfo = $this->ca->clientCert($commonName);

                $this->storage->addCertificate(
                    $userId,
                    $commonName,
                    $displayName,
                    new DateTime(sprintf('@%d', $certInfo['valid_from'])),
                    new DateTime(sprintf('@%d', $certInfo['valid_to']))
                );

                $this->storage->addUserMessage(
                    $userId,
                    'notification',
                    sprintf('new certificate "%s" generated by user', $displayName)
                );

                return new ApiResponse('add_client_certificate', $certInfo, 201);
            }
        );

        /*
         * This provides the CA (public) certificate and the "tls-auth" key
         * for this instance. The API call has a terrible name...
         */
        $service->get(
            '/server_info',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $serverInfo = [
                    'ta' => $this->tlsAuth->get(),
                    'ca' => $this->ca->caCert(),
                ];

                return new ApiResponse('server_info', $serverInfo);
            }
        );

        $service->post(
            '/add_server_certificate',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                $commonName = InputValidation::serverCommonName($request->getPostParameter('common_name'));

                $certInfo = $this->ca->serverCert($commonName);
                // add TLS Auth
                $certInfo['ta'] = $this->tlsAuth->get();
                $certInfo['ca'] = $this->ca->caCert();

                return new ApiResponse('add_server_certificate', $certInfo, 201);
            }
        );

        $service->post(
            '/delete_client_certificate',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
                if (false === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
                    return new ApiErrorResponse('delete_client_certificate', 'certificate does not exist');
                }

                $this->storage->addUserMessage(
                    $certInfo['user_id'],
                    'notification',
                    sprintf('certificate "%s" deleted by user', $certInfo['display_name'])
                );

                $this->storage->deleteCertificate($commonName);

                return new ApiResponse('delete_client_certificate');
            }
        );

        $service->get(
            '/client_certificate_list',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('client_certificate_list', $this->storage->getCertificates($userId));
            }
        );

        $service->get(
            '/client_certificate_info',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $commonName = InputValidation::commonName($request->getQueryParameter('common_name'));

                return new ApiResponse('client_certificate_info', $this->storage->getUserCertificateInfo($commonName));
            }
        );
    }
}
