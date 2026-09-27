<?php

namespace BS\XFWebSockets\Broadcasting;

class Channel
{
    /**
     * The channel's name.
     *
     * @var string
     */
    public string $name;

    public function __construct(mixed $name)
    {
        // check if channel has name pattern constant
        if (defined('static::NAME_PATTERN')) {
            $name = $this->patternedName(
                constant('static::NAME_PATTERN'),
                $name
            );
        }

        $this->name = (string)$name;
    }

    /**
     * Convert the channel instance to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * This function allowed to format passed values into the patterned name.
     *
     * For example:
     * ('User.{id}', 1) will return 'User.1'
     * ('User.{id}', 'User.1') will return 'User.1'
     * ('User.{id}', [1]) will return 'User.1'
     * ('User.{id}', [1, 2]) will return 'User.1'
     * ('User.{id}.Post.{id}', [1, 2]) will return 'User.1.Post.2'
     * ('User.{id}.Post.{id}', 'User.1.Post.2') will return 'User.1.Post.2'
     * and so on.
     *
     * @param  string  $pattern
     * @param  mixed  $values
     * @return string
     */
    protected function patternedName(
        string $pattern,
        mixed $values
    ): string {
        if (empty($values)) {
            return $pattern;
        }

        // Handle the case where $values is a matching string
        if (is_string($values)) {
            $placeholders = [];
            // Extract placeholders from the pattern
            preg_match_all('/\{(\w+)\}/', $pattern, $placeholders);
            $regex = '/^' . preg_quote($pattern, '/') . '$/';
            foreach ($placeholders[0] as $placeholder) {
                $regex = str_replace(
                    preg_quote($placeholder, '/'),
                    '([^.]+)',
                    $regex
                );
            }
            if (preg_match($regex, $values)) {
                return $values;
            }
        }

        // Normalize $values into an array
        $values = match (true) {
            is_string($values) => explode('.', $values),
            is_int($values) => [$values],
            default => $values
        };

        $index = 0;
        // Replace placeholders in the pattern with values from the array
        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn($matches) => $values[$index++] ?? $matches[0],
            $pattern
        );
    }
}
