function get_current_version {
    param (
        [string]$file = "index.php"
    )
    
    if (!(Test-Path $file)) {
        return $null
    }
    
    $content = Get-Content $file -Raw
    $versionMatch = $content | Select-String -Pattern "Version: (\d+\.\d+\.\d+|\d+\.\d+|\d+)"
    
    if ($versionMatch) {
        return $versionMatch.Matches.Groups[1].Value
    }
    
    return $null
}

function get_next_version {
    param (
        [string]$current_version
    )
    
    if ($current_version) {
        $segments = $current_version -split "\."
        
        # Incrementar el segmento apropiado según la longitud
        if ($segments.Length -eq 3) {
            $segments[2] = [int]$segments[2] + 1  # Incrementa el patch (Z) en X.Y.Z
        } elseif ($segments.Length -eq 2) {
            $segments[1] = [int]$segments[1] + 1  # Incrementa el minor (Y) en X.Y
        } else {
            $segments[0] = [int]$segments[0] + 1  # Incrementa el major (X)
        }
        
        return $segments -join "."
    }
    
    return $null
}

# Procesar argumentos de línea de comandos
if ($args.Count -gt 0) {
    switch ($args[0]) {
        "--get_version" {
            $version = get_current_version
            if ($version) {
                Write-Output $version
            } else {
                Write-Output "No se encontró una version válida"
            }
            return
        }
        "--get_next_version" {
            $current = get_current_version
            if ($current) {
                $next = get_next_version $current
                Write-Output $next
            } else {
                Write-Output "No se encontró una version válida"
            }
            return
        }
    }
}

# Script principal de actualización
$file = "index.php"

# Verificar si el archivo existe
if (!(Test-Path $file)) {
    Write-Output "El archivo $file no existe."
    exit 1
}

$current_version = get_current_version
if ($current_version) {
    $new_version = get_next_version $current_version
    
    # Usar una ruta absoluta para el archivo temporal
    $tempFile = Join-Path (Get-Location).Path "index.php.tmp"
    Write-Output "Intentando crear archivo temporal en: $tempFile"
    
    $newContent = (Get-Content $file -Raw) -replace "Version:\s*$current_version", "Version: $new_version"
    
    try {
        # Intentar crear el archivo temporal primero
        "" | Set-Content -Path $tempFile
        if (Test-Path $tempFile) {
            [System.IO.File]::WriteAllText($tempFile, $newContent)
            
            $verificationContent = Get-Content $tempFile -Raw
            if ($verificationContent -match [regex]::Escape("Version: $new_version")) {
                Move-Item -Path $tempFile -Destination $file -Force
                Write-Output "Version actualizada de $current_version a $new_version"
            } else {
                Write-Output "Error: El contenido del archivo temporal no es correcto"
                if (Test-Path $tempFile) { Remove-Item $tempFile }
            }
        } else {
            Write-Output "Error: No se tienen permisos para crear archivos en este directorio"
        }
    }
    catch {
        Write-Output "Error durante la operación: $_"
        if (Test-Path $tempFile) { Remove-Item $tempFile }
    }
} else {
    Write-Output "No se encontró una version válida en el archivo."
}