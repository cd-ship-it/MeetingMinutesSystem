<?php

declare(strict_types=1);

/**
 * Extract plain text from uploaded files on the server so we can send only text to OpenAI
 * (reducing payload vs uploading the full file).
 * Supports: txt, docx, pdf (when pdftotext is available), rtf (basic).
 */
function extract_text_from_file(string $filePath, string $extension): ?string {
    if (!is_file($filePath) || !is_readable($filePath)) {
        return null;
    }
    $ext = strtolower($extension);
    if ($ext === 'txt') {
        return extract_txt($filePath);
    }
    if ($ext === 'docx') {
        return extract_docx($filePath);
    }
    if ($ext === 'pdf') {
        return extract_pdf($filePath);
    }
    if ($ext === 'rtf') {
        return extract_rtf($filePath);
    }
    return null;
}

function extract_txt(string $filePath): ?string {
    $s = @file_get_contents($filePath);
    if ($s === false) {
        return null;
    }
    $enc = mb_detect_encoding($s, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $s = mb_convert_encoding($s, 'UTF-8', $enc);
    }
    return trim($s) ?: null;
}

function extract_docx(string $filePath): ?string {
    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::RDONLY) !== true) {
        return null;
    }
    $index = $zip->locateName('word/document.xml');
    if ($index === false) {
        $zip->close();
        return null;
    }
    $xml = $zip->getFromIndex($index);
    $zip->close();
    if ($xml === false || $xml === '') {
        return null;
    }
    $xml = preg_replace('/>\s*</', '> <', $xml);
    // Preserve paragraph boundaries: Word uses <w:p> for paragraphs
    $xml = preg_replace('/<\/w:p>/i', "\n", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text); // collapse spaces/tabs only, keep newlines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    $text = trim($text);
    return $text !== '' ? $text : null;
}

function extract_pdf(string $filePath): ?string {
    $pdftotext = trim((string) exec('which pdftotext 2>/dev/null'));
    if ($pdftotext === '') {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'pdf');
    if ($tmp === false) {
        return null;
    }
    $esc = escapeshellarg($filePath);
    $out = escapeshellarg($tmp . '.txt');
    exec("pdftotext -layout -enc UTF-8 $esc $out 2>/dev/null", $_, $code);
    $text = is_file($tmp . '.txt') ? @file_get_contents($tmp . '.txt') : false;
    @unlink($tmp . '.txt');
    @unlink($tmp);
    if ($text === false || $code !== 0) {
        return null;
    }
    $text = trim($text);
    return $text !== '' ? $text : null;
}

function extract_rtf(string $filePath): ?string {
    $s = @file_get_contents($filePath);
    if ($s === false) {
        return null;
    }
    $text = strip_rtf($s);
    $enc = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $text = mb_convert_encoding($text, 'UTF-8', $enc);
    }
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", trim($text));
    return $text !== '' ? $text : null;
}

/**
 * Remove RTF control words and groups; leave readable text.
 */
function strip_rtf(string $rtf): string {
    $out = '';
    $len = strlen($rtf);
    $i = 0;
    $skip = 0;
    while ($i < $len) {
        if ($skip > 0) {
            $skip--;
            $i++;
            continue;
        }
        $c = $rtf[$i];
        if ($c === '\\') {
            $i++;
            if ($i >= $len) {
                break;
            }
            $next = $rtf[$i];
            if ($next === '{' || $next === '}' || $next === '\\') {
                $out .= $next;
                $i++;
                continue;
            }
            $word = '';
            while ($i < $len) {
                $ch = $rtf[$i];
                if (!ctype_alpha($ch) && $ch !== '-') {
                    break;
                }
                $word .= $ch;
                $i++;
            }
            $param = '';
            if ($i < $len && ($rtf[$i] === '-' || ctype_digit($rtf[$i]))) {
                if ($rtf[$i] === '-') {
                    $param .= $rtf[$i];
                    $i++;
                }
                while ($i < $len && ctype_digit($rtf[$i])) {
                    $param .= $rtf[$i];
                    $i++;
                }
            }
            if ($i < $len && $rtf[$i] === ' ') {
                $i++;
            }
            if ($word === 'par' || $word === 'line') {
                $out .= "\n";
            }
            continue;
        }
        if ($c === '{') {
            $i++;
            continue;
        }
        if ($c === '}') {
            $i++;
            continue;
        }
        if ($c === "\n" || $c === "\r") {
            $out .= ' ';
            $i++;
            continue;
        }
        $out .= $c;
        $i++;
    }
    return $out;
}
