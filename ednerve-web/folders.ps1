$directories = @(
    "src\app",
    "src\layouts",
    "src\layouts\workspaces",
    "src\modules\dashboard",
    "src\modules\institutions\pages",
    "src\authorization"
)

foreach ($directory in $directories) {
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
    Write-Host "Created/verified: $directory"
}