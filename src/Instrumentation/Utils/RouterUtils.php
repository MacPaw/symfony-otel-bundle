<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RouterUtils
{
    private ?Request $mainRequest;
    private ?Request $currentRequest;
    private ?Request $parentRequest;

    public function __construct(
        RequestStack $requestStack,
    ) {
        $this->currentRequest = $requestStack->getCurrentRequest();
        $this->mainRequest = $requestStack->getMainRequest();
        $this->parentRequest = $requestStack->getParentRequest();
    }

    public function getRouteName(): ?string
    {
        $request = $this->currentRequest ?? $this->mainRequest ?? $this->parentRequest;

        $routeName = $request?->attributes->get('_route');
        assert(is_string($routeName) || is_null($routeName));

        return $routeName;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRouteParams(): ?array
    {
        $request = $this->currentRequest ?? $this->mainRequest ?? $this->parentRequest;

        $routeParams = $request?->attributes->get('_route_params');

        if (!is_array($routeParams)) {
            return null;
        }

        $result = [];
        foreach ($routeParams as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
