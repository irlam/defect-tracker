param([string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot))

Add-Type -AssemblyName System.Drawing

function New-RoundedRectanglePath([float]$X, [float]$Y, [float]$Width, [float]$Height, [float]$Radius) {
    $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $diameter = $Radius * 2
    $path.AddArc($X, $Y, $diameter, $diameter, 180, 90)
    $path.AddArc($X + $Width - $diameter, $Y, $diameter, $diameter, 270, 90)
    $path.AddArc($X + $Width - $diameter, $Y + $Height - $diameter, $diameter, $diameter, 0, 90)
    $path.AddArc($X, $Y + $Height - $diameter, $diameter, $diameter, 90, 90)
    $path.CloseFigure()
    return $path
}

function New-GuardianBitmap([int]$Size) {
    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.ScaleTransform($Size / 512.0, $Size / 512.0)

    $background = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(7, 17, 31))
    $graphics.FillRectangle($background, 0, 0, 512, 512)
    $background.Dispose()

    $halo = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(36, 19, 56, 91))
    $graphics.FillEllipse($halo, 45, 35, 422, 422)
    $halo.Dispose()

    $shieldPath = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $shieldPath.AddLines([System.Drawing.PointF[]]@(
        [System.Drawing.PointF]::new(256, 74),
        [System.Drawing.PointF]::new(408, 128),
        [System.Drawing.PointF]::new(408, 244)
    ))
    $shieldPath.AddBezier(408, 244, 408, 345, 350, 420, 256, 458)
    $shieldPath.AddBezier(256, 458, 162, 420, 104, 345, 104, 244)
    $shieldPath.AddLine(104, 244, 104, 128)
    $shieldPath.CloseFigure()
    $shieldFill = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(7, 24, 39))
    $shieldPen = [System.Drawing.Pen]::new([System.Drawing.Color]::FromArgb(34, 211, 238), 30)
    $shieldPen.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $graphics.FillPath($shieldFill, $shieldPath)
    $graphics.DrawPath($shieldPen, $shieldPath)

    $checkPen = [System.Drawing.Pen]::new([System.Drawing.Color]::FromArgb(110, 231, 183), 35)
    $checkPen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $checkPen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $checkPen.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $graphics.DrawLines($checkPen, [System.Drawing.PointF[]]@(
        [System.Drawing.PointF]::new(169, 268),
        [System.Drawing.PointF]::new(227, 326),
        [System.Drawing.PointF]::new(346, 194)
    ))

    $alertOuter = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(253, 230, 138))
    $alertInner = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(245, 158, 11))
    $alertCore = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(255, 247, 214))
    $graphics.FillEllipse($alertOuter, 327, 105, 80, 80)
    $graphics.FillEllipse($alertInner, 337, 115, 60, 60)
    $graphics.FillEllipse($alertCore, 357, 135, 20, 20)

    $alertCore.Dispose(); $alertInner.Dispose(); $alertOuter.Dispose()
    $checkPen.Dispose(); $shieldPen.Dispose(); $shieldFill.Dispose(); $shieldPath.Dispose()
    $graphics.Dispose()
    return $bitmap
}

function Save-GuardianPng([string]$Path, [int]$Size) {
    $directory = Split-Path -Parent $Path
    if (!(Test-Path -LiteralPath $directory)) { New-Item -ItemType Directory -Path $directory | Out-Null }
    $bitmap = New-GuardianBitmap $Size
    $bitmap.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $bitmap.Dispose()
}

$pngTargets = @{
    'favicons/favicon-96x96.png' = 96
    'favicons/apple-touch-icon.png' = 180
    'favicons/web-app-manifest-192x192.png' = 192
    'favicons/web-app-manifest-512x512.png' = 512
    'assets/brand/defect-guardian-mark.png' = 1024
    'assets/icons/android/android-launchericon-48-48.png' = 48
    'assets/icons/android/android-launchericon-72-72.png' = 72
    'assets/icons/android/android-launchericon-96-96.png' = 96
    'assets/icons/android/android-launchericon-144-144.png' = 144
    'assets/icons/android/android-launchericon-192-192.png' = 192
    'assets/icons/android/android-launchericon-512-512.png' = 512
}

foreach ($target in $pngTargets.GetEnumerator()) {
    Save-GuardianPng (Join-Path $ProjectRoot $target.Key) $target.Value
}

$iosDirectory = Join-Path $ProjectRoot 'assets/icons/ios'
Get-ChildItem -LiteralPath $iosDirectory -Filter '*.png' | ForEach-Object {
    $size = 0
    if ([int]::TryParse($_.BaseName, [ref]$size) -and $size -gt 0) {
        Save-GuardianPng $_.FullName $size
    }
}

# A compact multi-size ICO using PNG-compressed icon entries.
$iconSizes = @(16, 32, 48)
$streams = @()
foreach ($size in $iconSizes) {
    $bitmap = New-GuardianBitmap $size
    $stream = [System.IO.MemoryStream]::new()
    $bitmap.Save($stream, [System.Drawing.Imaging.ImageFormat]::Png)
    $bitmap.Dispose()
    $streams += ,$stream
}
$icoPath = Join-Path $ProjectRoot 'favicons/favicon.ico'
$file = [System.IO.File]::Open($icoPath, [System.IO.FileMode]::Create)
$writer = [System.IO.BinaryWriter]::new($file)
$writer.Write([uint16]0); $writer.Write([uint16]1); $writer.Write([uint16]$streams.Count)
$offset = 6 + (16 * $streams.Count)
for ($index = 0; $index -lt $streams.Count; $index++) {
    $size = $iconSizes[$index]
    $writer.Write([byte]($(if ($size -eq 256) { 0 } else { $size })))
    $writer.Write([byte]($(if ($size -eq 256) { 0 } else { $size })))
    $writer.Write([byte]0); $writer.Write([byte]0)
    $writer.Write([uint16]1); $writer.Write([uint16]32)
    $writer.Write([uint32]$streams[$index].Length); $writer.Write([uint32]$offset)
    $offset += [int]$streams[$index].Length
}
foreach ($stream in $streams) {
    $writer.Write($stream.ToArray())
    $stream.Dispose()
}
$writer.Dispose(); $file.Dispose()

Write-Output "Generated Defect Guardian favicon, app and presentation assets."
