# Lint all PHP files in the repository and output results to lint-report.txt
$php = "C:\xampp\php\php.exe"
if (-not (Test-Path $php)) {
  Write-Error "PHP binary not found at $php. Update the path to your PHP executable."
  exit 2
}
$repo = Split-Path -Parent $MyInvocation.MyCommand.Definition
$files = Get-ChildItem -Path $repo -Recurse -Include *.php -File | Where-Object { $_.FullName -notmatch "vendor\\|node_modules" }
$report = Join-Path $repo 'lint-report.txt'
"PHP Lint report generated on $(Get-Date -Format 'u')`n" | Out-File $report -Encoding utf8
foreach ($f in $files) {
  $path = $f.FullName
  $out = & $php -l $path 2>&1
  "$path`n$out`n`n" | Out-File $report -Append -Encoding utf8
}
Write-Output "Lint complete. See: $report"