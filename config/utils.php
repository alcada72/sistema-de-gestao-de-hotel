<?php

function generateAvatar($text): string
{
  $text = strtolower(trim($text));

  $text = preg_replace('/[àáâãäå]/u', 'a', $text);
  $text = preg_replace('/[èéêë]/u', 'e', $text);
  $text = preg_replace('/[ìíîï]/u', 'i', $text);
  $text = preg_replace('/[òóôõö]/u', 'o', $text);
  $text = preg_replace('/[ùúûü]/u', 'u', $text);
  $text = preg_replace('/[ç]/u', 'c', $text);

  $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
  $text = preg_replace('/[\s-]+/', '-', $text);

  return substr(md5(uniqid($text, true)), 0, 6) . '.png';
}


function generateSlug($text): string
{
  $text = strtolower(trim($text));

  $text = preg_replace('/[àáâãäå]/u', 'a', $text);
  $text = preg_replace('/[èéêë]/u', 'e', $text);
  $text = preg_replace('/[ìíîï]/u', 'i', $text);
  $text = preg_replace('/[òóôõö]/u', 'o', $text);
  $text = preg_replace('/[ùúûü]/u', 'u', $text);
  $text = preg_replace('/[ç]/u', 'c', $text);

  $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
  $text = preg_replace('/[\s-]+/', '-', $text);

  return $text . '-' . substr(md5(uniqid('', true)), 0, 6);
}

function e($value): string
{
  return htmlspecialchars(
    (string) ($value ?? ""),
    ENT_QUOTES,
    "UTF-8"
  );
}
