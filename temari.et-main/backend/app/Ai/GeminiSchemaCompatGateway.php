<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;

/**
 * Gemini rejects tool function declarations carrying `additionalProperties`
 * (its Schema proto has no such field), but the SDK's ObjectSchema wrapper
 * stamps `additionalProperties: false` onto every nested object node — so
 * any tool with an object-typed parameter (e.g. the exam builder's question
 * rows) 400s before the model ever runs. This gateway scrubs the keyword
 * from mapped declarations; registered for the gemini driver in
 * AppServiceProvider. Drop it once laravel/ai strips the field itself.
 */
class GeminiSchemaCompatGateway extends GeminiGateway
{
    protected function mapTool(Tool $tool): array
    {
        $definition = parent::mapTool($tool);

        if (isset($definition['parameters'])) {
            $definition['parameters'] = $this->stripAdditionalProperties($definition['parameters']);
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function stripAdditionalProperties(array $schema): array
    {
        unset($schema['additionalProperties']);

        foreach (['properties', 'items', 'anyOf'] as $key) {
            if (! isset($schema[$key]) || ! is_array($schema[$key])) {
                continue;
            }

            if ($key === 'items') {
                $schema['items'] = $this->stripAdditionalProperties($schema['items']);

                continue;
            }

            foreach ($schema[$key] as $name => $child) {
                if (is_array($child)) {
                    $schema[$key][$name] = $this->stripAdditionalProperties($child);
                }
            }
        }

        return $schema;
    }
}
