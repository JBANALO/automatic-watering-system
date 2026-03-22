$ErrorActionPreference = 'Stop'

$arduino = 'C:\Program Files\Arduino CLI\arduino-cli.exe'
$sketchPath = 'c:\xamppp\htdocs\automatic-watering-system\soil_sensor_calibrate'
$fqbn = 'esp32:esp32:esp32'

if (-not (Test-Path $arduino)) {
    Write-Host 'Arduino CLI not found at expected path.' -ForegroundColor Red
    exit 1
}

Write-Host 'Compiling sketch...' -ForegroundColor Cyan
& $arduino compile --fqbn $fqbn $sketchPath

$port = $null
try {
    $boardJson = & $arduino board list --format json | Out-String
    if ($boardJson -and $boardJson.Trim().StartsWith('[')) {
        $boards = $boardJson | ConvertFrom-Json
        if ($boards.Count -gt 0 -and $boards[0].address) {
            $port = $boards[0].address
        }
    }
} catch {
    # Fall back to serial port detection below.
}

if (-not $port) {
    $serial = Get-CimInstance Win32_SerialPort | Select-Object -First 1 DeviceID
    if ($serial -and $serial.DeviceID) {
        $port = $serial.DeviceID
    }
}

if (-not $port) {
    Write-Host 'No serial port detected. Plug ESP32 and retry.' -ForegroundColor Yellow
    exit 1
}

Write-Host "Using port: $port" -ForegroundColor Green

Write-Host 'Uploading sketch...' -ForegroundColor Cyan
& $arduino upload -p $port --fqbn $fqbn $sketchPath

Write-Host 'Opening serial monitor at 115200...' -ForegroundColor Cyan
& $arduino monitor -p $port -c baudrate=115200
