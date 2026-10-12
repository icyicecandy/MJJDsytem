$ErrorActionPreference = 'Stop'
$reviewDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourcePath = Join-Path $reviewDir 'CUSTOMER RELATIONSHIP MANAGEMENT SYSTEM FOR MJJD TELECOM.docx'
$pdfPath = Join-Path $reviewDir 'CUSTOMER RELATIONSHIP MANAGEMENT SYSTEM FOR MJJD TELECOM.pdf'
$logPath = Join-Path $reviewDir 'render-word.log'
$donePath = Join-Path $reviewDir 'render-word.done'
$errorPath = Join-Path $reviewDir 'render-word.error'

Remove-Item -LiteralPath $donePath, $errorPath -Force -ErrorAction SilentlyContinue
Set-Content -LiteralPath $logPath -Value "Starting Word PDF export at $(Get-Date -Format o)"

$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($sourcePath, $false, $true)
    Add-Content -LiteralPath $logPath -Value "Opened document at $(Get-Date -Format o)"
    $pageCount = $document.ComputeStatistics(2)
    $wordCount = $document.ComputeStatistics(0)
    Add-Content -LiteralPath $logPath -Value "Pages=$pageCount Words=$wordCount"
    $document.ExportAsFixedFormat($pdfPath, 17)
    Add-Content -LiteralPath $logPath -Value "Exported PDF at $(Get-Date -Format o)"
    Set-Content -LiteralPath $donePath -Value "Pages=$pageCount Words=$wordCount PDF=$pdfPath"
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
