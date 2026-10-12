$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$reviewDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourcePath = Join-Path $reviewDir 'CUSTOMER RELATIONSHIP MANAGEMENT SYSTEM FOR MJJD TELECOM.docx'
$outputDir = Join-Path $reviewDir 'page-snapshots'
$logPath = Join-Path $reviewDir 'capture-pages.log'
$pagesToCapture = @(39)

New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
Set-Content -LiteralPath $logPath -Value "Starting page capture at $(Get-Date -Format o)"

$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $true
    $word.WindowState = 2
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($sourcePath, $false, $true)
    $pageCount = $document.ComputeStatistics(2)
    Add-Content -LiteralPath $logPath -Value "Pages=$pageCount"

    foreach ($page in $pagesToCapture) {
        if ($page -gt $pageCount) { continue }
        $start = $document.GoTo(1, 1, $page)
        if ($page -lt $pageCount) {
            $next = $document.GoTo(1, 1, $page + 1)
            $endPosition = $next.Start - 1
            [System.Runtime.InteropServices.Marshal]::ReleaseComObject($next) | Out-Null
        }
        else {
            $endPosition = $document.Content.End - 1
        }
        $range = $document.Range($start.Start, $endPosition)
        [System.Windows.Forms.Clipboard]::Clear()
        $range.Select()
        $word.Selection.CopyAsPicture()
        Start-Sleep -Milliseconds 800
        $image = [System.Windows.Forms.Clipboard]::GetImage()
        if ($null -eq $image) {
            Add-Content -LiteralPath $logPath -Value "Page ${page}: clipboard did not contain a raster image"
            $dataObject = [System.Windows.Forms.Clipboard]::GetDataObject()
            foreach ($format in $dataObject.GetFormats()) {
                $data = $dataObject.GetData($format)
                $typeName = if ($null -eq $data) { '<null>' } else { $data.GetType().FullName }
                Add-Content -LiteralPath $logPath -Value "  Format=$format Type=$typeName"
            }
        }
        else {
            $path = Join-Path $outputDir ("page-{0:D3}.png" -f $page)
            $image.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
            Add-Content -LiteralPath $logPath -Value "Page ${page}: saved $path"
            $image.Dispose()
        }
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($range) | Out-Null
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($start) | Out-Null
    }
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
