<?php declare(strict_types = 1);

/*
 * instructions: 805.8K
 * cycles: 1.223M
 * memory: 105.6KiB
 */

$text = str_repeat('the quick brown fox jumps over the lazy dog ', 200);

return array_count_values(str_word_count($text, 1));
