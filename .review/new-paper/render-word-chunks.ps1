$ErrorActionPreference = 'Stop'
$reviewDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourcePath = Join-Path $reviewDir 'CUSTOMER RELATIONSHIP MANAGEMENT SYSTEM FOR MJJD TELECOM.docx'
$chunkDir = Join-Path $reviewDir 'pdf-chunks'
$logPath = Join-Path $reviewDir 'render-chunks.log'
$donePath = Join-Path $reviewDir 'render-chunks.done'
$errorPath = Join-Path $reviewDir 'render-chunks.error'

New-Item -ItemType Directory -Force -Path $chunkDir | Out-Null
Remove-Item -LiteralPath $donePath, $errorPath -Force -ErrorAction SilentlyContinue
Set-Content -LiteralPath $logPath -Value "Starting chunked Word PDF export at $(Get-Date -Format o)"

$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($sourcePath, $false, $true)
    Add-Content -LiteralPath $logPath -Value "Opened document at $(Get-Date -Format o)"
    $pageCount = $document.ComputeStatistics(2)
    Add-Content -LiteralPath $logPath -Value "Pages=$pageCount"

    for ($from = 1; $from -le $pageCount; $from += 20) {
        $to = [Math]::Min($from + 19, $pageCount)
        $chunkPath = Join-Path $chunkDir ("pages-{0:D3}-{1:D3}.pdf" -f $from, $to)
        Add-Content -LiteralPath $logPath -Value "Exporting pages $from-$to at $(Get-Date -Format o)"
        $document.ExportAsFixedFormat($chunkPath, 17, $false, 0, 3, $from, $to, 0, $true, $true, 1, $true, $true, $false)
        Add-Content -LiteralPath $logPath -Value "Finished pages $from-$to at $(Get-Date -Format o)"
    }

    Set-Content -LiteralPath $donePath -Value "Pages=$pageCount Chunks=$([Math]::Ceiling($pageCount / 20.0))"
}
catch {
    $_ | Out-String | Set-Content -LiteralPath $errorPath
    $_ | Out-String | Add-Content -LiteralPath $logPath
}
finally {
    if ($null -ne $document) {
        $document.Close($false)
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($document) | Out-Null
    }
    if ($null -ne $word) {
        $word.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
