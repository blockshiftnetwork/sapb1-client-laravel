<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Raw OData filter that allows unvalidated OData query strings.
 *
 * **SECURITY WARNING**: This filter bypasses all escaping mechanisms and directly
 * injects the provided string into the OData query. This filter should NEVER accept
 * user-controlled input. Only use with hardcoded strings or strings that have been
 * thoroughly validated and sanitized. Passing unvalidated user input can lead to
 * OData injection attacks.
 *
 * @example
 * // ✅ Safe usage - hardcoded string
 * $query = (new ODataQuery)->where(new Raw("contains(CardName, 'Test')"));
 * @example
 * // ❌ DANGEROUS - user input
 * $userInput = $_GET['filter']; // NEVER DO THIS
 * $query = (new ODataQuery)->where(new Raw($userInput));
 */
class Raw extends Filter
{
    private string $string;

    /**
     * Create a new raw filter instance.
     *
     * **WARNING**: This method does not validate or escape the input string. Ensure
     * the string is safe and does not contain user-controlled data.
     *
     * @param  string  $string  The raw OData filter string to inject (must be trusted/pre-validated)
     */
    public function __construct(string $string)
    {
        $this->string = $string;
    }

    #[Override]
    public function execute(): string
    {
        return $this->string;
    }
}
