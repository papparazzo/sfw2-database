# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

### [4.1.1] - 2025-04-08
#### Fixed
- Return types fixed

### [4.1.0] - 2025-04-05
#### Changed
- PHPStan issues

### [4.0.1] - 2024-03-15
#### Fixed
- Bugfix in QueryHelper::selectKeyValue

### [4.0.0] - 2024-02-29
#### Changed
- Database and DatabaseInterface refactored

#### Added
- QueryHelper added

### [3.0.1] - 2024-01-10
#### Fixed
- propper replacement of TABLE_PREFIX in selectKeyValue and selectKeyValues 

### [3.0.0] - 2024-01-01
#### Changed
- PHP-Version 8.2
- Exception renamed into DatabaseException

#### Fixed
- Identifier-name checking rather than escapting

### [2.1.0] - 2023-12-27
#### Changed
- Respect DateTime-interface in Database::escape

### [2.0.0] - 2023-11-11
#### Chnaged
- Switched to PDO

### [1.0.0] - 2020-06-29
#### Added
- Library released
