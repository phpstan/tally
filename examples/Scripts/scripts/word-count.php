<?php declare(strict_types = 1);

$text = str_repeat('the quick brown fox jumps over the lazy dog ', 200);

return array_count_values(str_word_count($text, 1));
