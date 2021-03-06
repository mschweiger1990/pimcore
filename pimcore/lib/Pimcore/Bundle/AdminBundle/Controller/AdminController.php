<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\AdminBundle\Controller;

use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Pimcore\Bundle\AdminBundle\Security\User\User as UserProxy;
use Pimcore\Controller\Controller;
use Pimcore\Model\User;
use Pimcore\Tool\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Translation\Exception\InvalidArgumentException;

abstract class AdminController extends Controller implements AdminControllerInterface
{
    /**
     * @inheritDoc
     */
    public function needsSessionDoubleAuthenticationCheck()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function needsStorageDoubleAuthenticationCheck()
    {
        return true;
    }

    /**
     * Get user from user proxy object which is registered on security component
     *
     * @param bool $proxyUser Return the proxy user (UserInterface) instead of the pimcore model
     *
     * @return UserProxy|User
     */
    protected function getUser($proxyUser = false)
    {
        $resolver = $this->get('pimcore_admin.security.token_storage_user_resolver');

        if ($proxyUser) {
            return $resolver->getUserProxy();
        } else {
            return $resolver->getUser();
        }
    }

    /**
     * Check user permission
     *
     * @param $permission
     *
     * @throws AccessDeniedHttpException
     */
    protected function checkPermission($permission)
    {
        if (!$this->getUser() || !$this->getUser()->isAllowed($permission)) {
            $this->get('monolog.logger.security')->error(
                'User {user} attempted to access {permission}, but has no permission to do so', [
                    'user'       => $this->getUser()->getName(),
                    'permission' => $permission
                ]
            );

            throw new AccessDeniedHttpException('Attempt to access ' . $permission . ', but has no permission to do so.');
        }
    }

    /**
     * Check permission against all controller actions. Can optionally exclude a list of actions.
     *
     * @param FilterControllerEvent $event
     * @param string $permission
     * @param array $unrestrictedActions
     */
    protected function checkActionPermission(FilterControllerEvent $event, string $permission, array $unrestrictedActions = [])
    {
        $actionName = null;
        $controller = $event->getController();

        if (is_array($controller) && count($controller) === 2 && is_string($controller[1])) {
            $actionName = $controller[1];
        }

        if (null === $actionName || !in_array($actionName, $unrestrictedActions)) {
            $this->checkPermission($permission);
        }
    }

    /**
     * Encodes data into JSON string
     *
     * @param mixed $data       The data to be encoded
     * @param array $context    Context to pass to serializer when using serializer component
     * @param int $options      Options passed to json_encode
     *
     * @return string
     */
    protected function encodeJson($data, array $context = [], $options = JsonResponse::DEFAULT_ENCODING_OPTIONS)
    {
        $serializer = $this->container->get('pimcore_admin.serializer');

        return $serializer->serialize($data, 'json', array_merge([
            'json_encode_options' => $options
        ], $context));
    }

    /**
     * Decodes a JSON string into an array/object
     *
     * @param mixed $json           The data to be decoded
     * @param bool  $associative    Whether to decode into associative array or object
     * @param array $context        Context to pass to serializer when using serializer component
     *
     * @return array|\stdClass
     */
    protected function decodeJson($json, $associative = true, array $context = [])
    {
        $serializer = $this->container->get('pimcore_admin.serializer');

        if ($associative) {
            $context['json_decode_associative'] = true;
        }

        return $serializer->decode($json, 'json', $context);
    }

    /**
     * Returns a JsonResponse that uses the admin serializer
     *
     * @param mixed $data    The response data
     * @param int   $status  The status code to use for the Response
     * @param array $headers Array of extra headers to add
     * @param array $context Context to pass to serializer when using serializer component
     *
     * @return JsonResponse
     */
    protected function json($data, $status = 200, $headers = [], $context = [])
    {
        $json = $this->encodeJson($data, $context);

        return new JsonResponse($json, $status, $headers, true);
    }

    /**
     * Check CSRF token
     *
     * @param Request $request
     *
     * @throws AccessDeniedHttpException
     *      if CSRF token does not match
     */
    protected function protectCsrf(Request $request)
    {
        // TODO use isCsrfTokenValid() and the native CSRF token storage?

        $csrfToken = Session::useSession(function (AttributeBagInterface $adminSession) {
            return $adminSession->get('csrfToken');
        });

        if (!$csrfToken || $csrfToken !== $request->headers->get('x_pimcore_csrf_token')) {
            $this->get('monolog.logger.security')->error('Detected CSRF attack on {request}', [
                'request' => $request->getPathInfo()
            ]);

            throw new AccessDeniedHttpException('Detected CSRF Attack! Do not do evil things with pimcore ... ;-)');
        }
    }

    /**
     * Translates the given message.
     *
     * @param string $id The message id (may also be an object that can be cast to string)
     * @param array $parameters An array of parameters for the message
     * @param string|null $domain The domain for the message or null to use the default
     * @param string|null $locale The locale or null to use the default
     *
     * @return string The translated string
     *
     * @throws InvalidArgumentException If the locale contains invalid characters
     */
    public function trans($id, array $parameters = [], $domain = 'admin', $locale = null)
    {
        $translator = $this->get('translator');

        return $translator->trans($id, $parameters, $domain, $locale);
    }
}
