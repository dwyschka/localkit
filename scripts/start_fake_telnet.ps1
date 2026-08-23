<#
.SYNOPSIS
    Starts the LocalKit Fake PetKit Telnet Server.

.DESCRIPTION
    Runs a mock PetKit Telnet daemon supporting D4SH (Feeder) and W7H (Fountain/Toilet) models
    for testing LocalKit firmware installations and device management commands.

.PARAMETER Model
    The PetKit model to simulate: 'D4SH' (or 'd') / 'W7H' (or 'w'). Default is 'D4SH'.

.PARAMETER Port
    Port number to listen on. Default is 23.

.PARAMETER HostAddress
    Host IP address to bind. Default is 0.0.0.0.

.PARAMETER Fail
    Simulate an installation failure for testing error handling.

.PARAMETER Env
    Optional path to a custom .env file.

.EXAMPLE
    .\start_fake_telnet.ps1 -Model D4SH
    .\start_fake_telnet.ps1 -m w -Port 2323
    .\start_fake_telnet.ps1 d
    .\start_fake_telnet.ps1 w -Fail
#>
[CmdletBinding()]
param (
    [Parameter(Position = 0)]
    [Alias('m')]
    [ValidateSet('D4SH', 'W7H', 'd', 'w' IgnoreCase = $true)]
    [string]$Model = 'D4SH',

    [Parameter()]
    [Alias('p')]
    [int]$Port = 23,

    [Parameter()]
    [Alias('h', 'Host')]
    [string]$HostAddress = '0.0.0.0',

    [Parameter()]
    [switch]$Fail,

    [Parameter()]
    [string]$Env,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$ExtraArgs
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# Locate Python 3 executable
$PyCmd = $null
if (Get-Command py -ErrorAction SilentlyContinue) {
    $PyCmd = @("py", "-3")
} elseif (Get-Command python3 -ErrorAction SilentlyContinue) {
    $PyCmd = @("python3")
} elseif (Get-Command python -ErrorAction SilentlyContinue) {
    $PyCmd = @("python")
} else {
    Write-Error "[ERROR] Python was not found in your PATH. Please install Python 3 or add it to PATH."
    exit 1
}

# Normalize model name
$NormalizedModel = switch ($Model.ToLower()) {
    { $_ -in @('w', 'w7h') } { 'W7H' }
    default { 'D4SH' }
}

$PyArgs = @(
    "$ScriptDir\fake_petkit_telnet.py",
    "--model", $NormalizedModel,
    "--host", $HostAddress,
    "--port", $Port
)

if ($Fail) {
    $PyArgs += "--fail"
}

if ($Env) {
    $PyArgs += @("--env", $Env)
}

if ($ExtraArgs) {
    $PyArgs += $ExtraArgs
}

& $PyCmd[0] ($PyCmd[1..($PyCmd.Length - 1)] + $PyArgs)
