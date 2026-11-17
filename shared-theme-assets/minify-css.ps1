param()

$baseDir = Split-Path $PSScriptRoot -Parent
$css = [System.IO.File]::ReadAllText("$baseDir\wp-content\themes\su-2016\assets\stylesheets\app.css")
$css = [System.Text.RegularExpressions.Regex]::Replace($css, '/\*.*?\*/', '', [System.Text.RegularExpressions.RegexOptions]::Singleline)
$css = [System.Text.RegularExpressions.Regex]::Replace($css, '\s*([\{\}:;,>+~])\s*', '$1')
$css = $css.Replace(';}', '}')
$css = [System.Text.RegularExpressions.Regex]::Replace($css, '\s+', ' ')
$css = $css.Trim()

[System.IO.File]::WriteAllText("$baseDir\wp-content\themes\su-2016\assets\stylesheets\app.min.css", $css, [System.Text.Encoding]::UTF8)
Write-Output "Generated app.min.css: $($css.Length) chars"
