# Overview

Packingo is a 3D Container Loading Optimization System designed to help users efficiently pack items into containers and trailers. The system processes input data from Excel files and generates optimized loading plans using advanced algorithms to maximize space utilization. It supports multiple container and trailer types, handles various units of measurement, and provides both web-based and desktop interfaces for user interaction.

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Core Technologies
- **Backend**: PHP 8.2+ with Composer dependency management
- **Frontend**: HTML5, CSS3, JavaScript (vanilla) with Bootstrap 5.3 styling
- **Excel Processing**: PHPSpreadsheet for reading and writing Excel files
- **File Conversion**: LibreOffice, ImageMagick, and Tesseract OCR integration
- **System Tools**: Nix-based package management for consistent deployments

## Application Structure
The system follows a modular PHP architecture with PSR-4 autoloading:
- **Classes Directory**: Contains core optimization algorithms under the `Packingo\` namespace
- **Assets**: Separated CSS, JavaScript, and static resources
- **Vendor Dependencies**: Managed through Composer for Excel processing and utility functions

## Data Processing Pipeline
1. **Input Handling**: Excel files containing item dimensions, weights, and packaging constraints
2. **Optimization Engine**: 3D bin packing algorithms that consider:
   - Container/trailer dimensions and weight limits
   - Item stackability rules
   - Multiple unit systems (metric/imperial)
   - Vehicle type constraints
3. **Output Generation**: Optimized loading plans exported to Excel format with visual representations

## User Interface Design
- **Web Interface**: Bootstrap-based responsive design with form validation
- **Desktop Application**: Cross-platform Tkinter GUI with multilingual support (Turkish/English)
- **Template System**: Auto-generates Excel templates for user input standardization

## Key Dependencies
- **PHPSpreadsheet**: Excel file manipulation and generation
- **Composer PCRE**: Regular expression handling for data validation
- **Matrix/Complex Libraries**: Mathematical operations for 3D calculations
- **ZipStream**: Efficient file compression and delivery

## Configuration Management
The system uses a flexible configuration approach:
- Composer autoloading for class management
- Modular CSS/JS architecture for maintainability
- Multi-language support infrastructure
- Customizable container and trailer type definitions

## Recent Configuration Updates
- **Vehicle Type**: "Tenteli" renamed to "Standart Tır" across all components
- **Lowbed Specifications**: Length increased from 20000mm to 25000mm for enhanced capacity
- **Algorithm Logic**: Preserved all optimization strategies with updated naming conventions

## Security Considerations
- Input validation for Excel file processing
- XSS protection through HTML purification libraries
- File upload restrictions and validation
- Sanitized output generation

# External Dependencies

## Package Management
- **Composer**: PHP dependency management and autoloading
- **PHPOffice/PhpSpreadsheet**: Excel file reading, writing, and manipulation
- **MarkBaker Libraries**: Complex number mathematics and matrix operations for 3D calculations

## Development Tools
- **HTML Purifier**: XSS protection and HTML sanitization
- **ZipStream-PHP**: Efficient file compression and streaming
- **PSR Standards**: HTTP message handling, caching interfaces, and factory patterns

## Runtime Requirements
- **PHP 8.2+**: Core language runtime with required extensions
- **LibreOffice**: Document conversion capabilities
- **ImageMagick**: Image processing and conversion
- **Tesseract OCR**: Optical character recognition for PDF/image processing
- **File System**: Writable directories for uploads, temp, and cache storage

## Replit Environment Setup (September 2025)
- **Development Server**: PHP built-in server on port 5000 (0.0.0.0:5000)
- **Production Deployment**: Autoscale configuration for efficient resource usage
- **System Dependencies**: All tools installed via Nix package manager
- **File Processing**: Full converter functionality with all required CLI tools
- **Status**: Fully operational and ready for production deployment