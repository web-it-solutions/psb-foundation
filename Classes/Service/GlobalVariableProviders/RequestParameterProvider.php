<?php
declare(strict_types=1);

/*
 * This file is part of PSB Foundation.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSB\PsbFoundation\Service\GlobalVariableProviders;

use JsonException;
use PSB\PsbFoundation\Utility\ContextUtility;
use PSB\PsbFoundation\Utility\StringUtility;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use function is_array;

/**
 * Class RequestParameterProvider
 *
 * @package PSB\PsbFoundation\Service\GlobalVariableProviders
 */
class RequestParameterProvider extends AbstractProvider
{
    /**
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     */
    public function getGlobalVariables(): array
    {
        $request = ContextUtility::getRequest();
        $parameters = $request?->getQueryParams() ?? [];
        $postParameters = $request?->getParsedBody();

        if (is_array($postParameters)) {
            ArrayUtility::mergeRecursiveWithOverrule($parameters, $postParameters);
        }

        array_walk_recursive($parameters, static function(&$item) {
            $item = is_string($item) ? StringUtility::convertString($item) : $item;
        });

        return $parameters;
    }
}
