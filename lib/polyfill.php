<?php

if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) {
    return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}