<?php

declare(strict_types=1);

namespace Switon\Http;

use ReflectionParameter;
use Switon\Binding\ValueResolverInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Core\MakerInterface;

/**
 * Resolves Criteria by delegating to method attributes that implement CriteriaAttributeInterface.
 *
 * Guidance: Keep this class as a thin dispatcher; each criteria attribute should own its own mutation logic.
 *
 * Road-signs:
 * - inspect action-method attributes
 * - keep only <code>CriteriaAttributeInterface</code> attributes
 * - create each attribute through the shared maker
 * - let the attribute mutate <code>Criteria</code> via <code>apply()</code>
 *
 * @see \Switon\Http\Criteria
 * @see \Switon\Http\CriteriaAttributeInterface
 */
class CriteriaResolver implements ValueResolverInterface
{
    #[Autowired] protected MakerInterface $maker;

    public function resolve(ReflectionParameter $parameter, string $type): Criteria
    {
        if ($type !== Criteria::class) {
            RuntimeException::raise(
                'CriteriaResolver only supports "{expected}", got "{actual}".',
                ['expected' => Criteria::class, 'actual' => $type]
            );
        }

        $criteria = new Criteria();
        $rMethod = $parameter->getDeclaringFunction();

        foreach ($rMethod->getAttributes() as $attribute) {
            if (!is_a($attribute->getName(), CriteriaAttributeInterface::class, true)) {
                continue;
            }

            /** @var CriteriaAttributeInterface $criteriaAttribute */
            $criteriaAttribute = $this->maker->make($attribute->getName(), $attribute->getArguments());
            $criteriaAttribute->apply($criteria, $parameter);
        }

        return $criteria;
    }
}
