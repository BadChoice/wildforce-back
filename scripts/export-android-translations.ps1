<#
.SYNOPSIS
Exports the shared Google Sheet translations to Android resource XML files.

.EXAMPLE
.\scripts\export-android-translations.ps1 -AndroidResPath F:\Git\doubleTriangleAndroid\app\src\main\res
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidateNotNullOrEmpty()]
    [string]$AndroidResPath,

    [ValidateNotNullOrEmpty()]
    [string]$SpreadsheetId = '1a3H2NRUXxVqaCjwBwMTCbxwFzDeZqlv_8I8C0EZhXho'
)

$ErrorActionPreference = 'Stop'

$locales = [ordered]@{
    'en' = 'values'
    'es' = 'values-es'
    'ca' = 'values-ca'
    'de' = 'values-de'
    'fr' = 'values-fr'
    'it' = 'values-it'
    'ja' = 'values-ja'
    'nl' = 'values-nl'
    'pt' = 'values-pt'
    'zh-Hans' = 'values-zh-rCN'
}

function Get-XmlDocument {
    param([Parameter(Mandatory)][string]$Path)

    $document = [System.Xml.XmlDocument]::new()
    $document.PreserveWhitespace = $true
    $document.Load($Path)

    return $document
}

function Get-ColumnIndex {
    param([Parameter(Mandatory)][string]$CellReference)

    $letters = [regex]::Match($CellReference, '^[A-Z]+').Value

    if ($letters -eq '') {
        throw "The spreadsheet cell reference '$CellReference' is invalid."
    }

    $index = 0
    foreach ($character in $letters.ToCharArray()) {
        $index = ($index * 26) + ([int][char]$character - 64)
    }

    return $index - 1
}

function Get-CellValue {
    param(
        [Parameter(Mandatory)][System.Xml.XmlElement]$Cell,
        [Parameter(Mandatory)][string[]]$SharedStrings,
        [Parameter(Mandatory)][System.Xml.XmlNamespaceManager]$NamespaceManager
    )

    $type = $Cell.GetAttribute('t')
    $value = $Cell.SelectSingleNode('./x:v', $NamespaceManager)

    if ($type -eq 's') {
        $index = [int]($value.InnerText)
        return $SharedStrings[$index]
    }

    if ($type -eq 'inlineStr') {
        return (($Cell.SelectNodes('.//x:t', $NamespaceManager) | ForEach-Object InnerText) -join '')
    }

    if ($null -eq $value) {
        return ''
    }

    return $value.InnerText
}

function Get-RowValues {
    param(
        [Parameter(Mandatory)][System.Xml.XmlElement]$Row,
        [Parameter(Mandatory)][string[]]$SharedStrings,
        [Parameter(Mandatory)][System.Xml.XmlNamespaceManager]$NamespaceManager
    )

    $values = @{}

    foreach ($cell in $Row.SelectNodes('./x:c', $NamespaceManager)) {
        $index = Get-ColumnIndex $cell.GetAttribute('r')
        $values[$index] = Get-CellValue $cell $SharedStrings $NamespaceManager
    }

    return $values
}

function Get-AndroidResourceName {
    param([Parameter(Mandatory)][string]$Key)

    $resourceName = $Key.Replace('-', '_').Replace(' ', '_').ToLowerInvariant()
    $resourceName = [regex]::Replace($resourceName, '[^a-z0-9_]', '_')

    if ($resourceName -eq '') {
        throw "The key '$Key' cannot be converted to an Android resource name."
    }

    if ([char]::IsDigit($resourceName[0])) {
        $resourceName = "key_$resourceName"
    }

    return $resourceName
}

function Escape-AndroidString {
    param([AllowEmptyString()][string]$Value)

    $escaped = $Value.Replace('\', '\\').Replace("`r`n", '\n').Replace("`n", '\n')
    $escaped = $escaped.Replace("'", "\'").Replace('"', '\"')

    return [System.Security.SecurityElement]::Escape($escaped)
}

function Get-AndroidXml {
    param(
        [Parameter(Mandatory)][object[]]$Translations,
        [Parameter(Mandatory)][string]$Locale
    )

    $lines = [System.Collections.Generic.List[string]]::new()
    $lines.Add('<?xml version="1.0" encoding="utf-8"?>')
    $lines.Add('<resources>')

    foreach ($translation in $Translations) {
        if ($null -ne $translation.Description) {
            $lines.Add(('    <!-- {0} -->' -f $translation.Description.Replace('--', '—')))
        }

        $value = Escape-AndroidString $translation.Translations[$Locale]
        $lines.Add(('    <string name="{0}" formatted="false">{1}</string>' -f $translation.ResourceName, $value))
    }

    $lines.Add('</resources>')

    return $lines -join "`n"
}

$resolvedResourcePath = (Resolve-Path -LiteralPath $AndroidResPath -ErrorAction Stop).Path
$temporaryDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ("wildforce-translations-" + [guid]::NewGuid().ToString('N'))
$workbookPath = Join-Path $temporaryDirectory 'translations.xlsx'
$archivePath = Join-Path $temporaryDirectory 'workbook'

try {
    New-Item -ItemType Directory -Path $temporaryDirectory | Out-Null
    Invoke-WebRequest -Uri "https://docs.google.com/spreadsheets/d/$SpreadsheetId/export?format=xlsx" -OutFile $workbookPath
    Expand-Archive -LiteralPath $workbookPath -DestinationPath $archivePath

    $spreadsheetNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
    $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
    $packageRelationshipNamespace = 'http://schemas.openxmlformats.org/package/2006/relationships'

    $sharedStrings = @()
    $sharedStringsPath = Join-Path $archivePath 'xl\sharedStrings.xml'
    if (Test-Path -LiteralPath $sharedStringsPath) {
        $sharedStringsXml = Get-XmlDocument $sharedStringsPath
        $sharedStringsNamespaces = [System.Xml.XmlNamespaceManager]::new($sharedStringsXml.NameTable)
        $sharedStringsNamespaces.AddNamespace('x', $spreadsheetNamespace)
        $sharedStrings = @($sharedStringsXml.SelectNodes('//x:si', $sharedStringsNamespaces) | ForEach-Object {
            ($_.SelectNodes('.//x:t', $sharedStringsNamespaces) | ForEach-Object InnerText) -join ''
        })
    }

    $workbookXml = Get-XmlDocument (Join-Path $archivePath 'xl\workbook.xml')
    $workbookNamespaces = [System.Xml.XmlNamespaceManager]::new($workbookXml.NameTable)
    $workbookNamespaces.AddNamespace('x', $spreadsheetNamespace)
    $workbookNamespaces.AddNamespace('r', $relationshipNamespace)
    $sheet = @($workbookXml.SelectNodes('//x:sheet', $workbookNamespaces) | Where-Object {
        $_.GetAttribute('name') -eq 'app'
    })[0]

    if ($null -eq $sheet) {
        throw "The Google Sheet does not contain the 'app' worksheet."
    }

    $relationshipsXml = Get-XmlDocument (Join-Path $archivePath 'xl\_rels\workbook.xml.rels')
    $relationshipNamespaces = [System.Xml.XmlNamespaceManager]::new($relationshipsXml.NameTable)
    $relationshipNamespaces.AddNamespace('r', $packageRelationshipNamespace)
    $targets = @{}
    foreach ($relationship in $relationshipsXml.SelectNodes('//r:Relationship', $relationshipNamespaces)) {
        $targets[$relationship.GetAttribute('Id')] = $relationship.GetAttribute('Target')
    }

    $relationshipId = $sheet.GetAttribute('id', $relationshipNamespace)
    $worksheetTarget = $targets[$relationshipId]
    if ([string]::IsNullOrWhiteSpace($worksheetTarget)) {
        throw "The Google Sheet worksheet 'app' is missing its XLSX relationship."
    }

    $worksheetXml = Get-XmlDocument (Join-Path $archivePath (Join-Path 'xl' $worksheetTarget))
    $worksheetNamespaces = [System.Xml.XmlNamespaceManager]::new($worksheetXml.NameTable)
    $worksheetNamespaces.AddNamespace('x', $spreadsheetNamespace)
    $rows = @($worksheetXml.SelectNodes('//x:sheetData/x:row', $worksheetNamespaces))
    if ($rows.Count -eq 0) {
        throw 'The Google Sheet is empty.'
    }

    $headerValues = Get-RowValues $rows[0] $sharedStrings $worksheetNamespaces
    $columns = @{}
    foreach ($index in $headerValues.Keys) {
        $columns[$headerValues[$index].Trim().ToLowerInvariant()] = $index
    }

    foreach ($column in @('key', 'description') + @($locales.Keys | ForEach-Object ToLowerInvariant)) {
        if (-not $columns.ContainsKey($column)) {
            throw "The Google Sheet is missing the required '$column' column."
        }
    }

    $translations = [System.Collections.Generic.List[object]]::new()
    $keys = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::Ordinal)
    $resourceNames = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::Ordinal)

    foreach ($row in $rows | Select-Object -Skip 1) {
        $values = Get-RowValues $row $sharedStrings $worksheetNamespaces
        $key = ([string]$values[$columns['key']]).Trim()

        if ($key -eq '') {
            if (@($values.Values | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }).Count -eq 0) {
                continue
            }

            throw 'Every non-empty Google Sheet row must have a key.'
        }

        if (-not $keys.Add($key)) {
            throw "The Google Sheet contains the duplicate key '$key'."
        }

        $resourceName = Get-AndroidResourceName $key
        if (-not $resourceNames.Add($resourceName)) {
            throw "The Android resource name '$resourceName' is duplicated."
        }

        $description = ([string]$values[$columns['description']]).Trim()
        $localizedValues = @{}
        foreach ($locale in $locales.Keys) {
            $localizedValues[$locale] = [string]$values[$columns[$locale.ToLowerInvariant()]]
        }

        $translations.Add([pscustomobject]@{
            Key = $key
            ResourceName = $resourceName
            Description = if ($description -eq '') { $null } else { $description }
            Translations = $localizedValues
        })
    }

    $xmlByLocale = @{}
    foreach ($locale in $locales.Keys) {
        $xmlByLocale[$locale] = Get-AndroidXml $translations.ToArray() $locale
    }

    foreach ($locale in $locales.Keys) {
        $directory = Join-Path $resolvedResourcePath $locales[$locale]
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
        Set-Content -LiteralPath (Join-Path $directory 'sheet_translations.xml') -Value $xmlByLocale[$locale] -Encoding utf8
    }

    Write-Output "Generated $($translations.Count) Android translations at $resolvedResourcePath."
} finally {
    if (Test-Path -LiteralPath $temporaryDirectory) {
        Remove-Item -LiteralPath $temporaryDirectory -Recurse -Force
    }
}
