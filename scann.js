// QR Code Scanner for Mobile
class QRScanner {
    constructor() {
        this.videoElement = null;
        this.canvasElement = null;
        this.canvasContext = null;
        this.scanning = false;
    }

    // Initialize scanner
    init(videoId, canvasId) {
        this.videoElement = document.getElementById(videoId);
        this.canvasElement = document.getElementById(canvasId);
        this.canvasContext = this.canvasElement.getContext('2d');
        
        return this;
    }

    // Start scanning
    async start() {
        try {
            // Request camera access
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            
            this.videoElement.srcObject = stream;
            this.scanning = true;
            
            // Start scanning loop
            this.scanLoop();
            
            return true;
        } catch (error) {
            console.error('Error accessing camera:', error);
            return false;
        }
    }

    // Stop scanning
    stop() {
        this.scanning = false;
        if (this.videoElement.srcObject) {
            this.videoElement.srcObject.getTracks().forEach(track => track.stop());
        }
    }

    // Scanning loop
    scanLoop() {
        if (!this.scanning) return;

        // Draw video frame to canvas
        this.canvasContext.drawImage(
            this.videoElement,
            0, 0,
            this.canvasElement.width,
            this.canvasElement.height
        );

        // Get image data from canvas
        const imageData = this.canvasContext.getImageData(
            0, 0,
            this.canvasElement.width,
            this.canvasElement.height
        );

        // Try to decode QR code
        try {
            const code = this.decodeQR(imageData);
            if (code) {
                this.onScanSuccess(code);
                return;
            }
        } catch (error) {
            // Continue scanning
        }

        // Continue scanning
        requestAnimationFrame(() => this.scanLoop());
    }

    // Simple QR decoder (for demo - in production use a library like jsQR)
    decodeQR(imageData) {
        // This is a simplified version
        // In production, use: https://github.com/cozmo/jsQR
        return null;
    }

    // Handle successful scan
    onScanSuccess(code) {
        // Stop scanning
        this.stop();
        
        // Handle the scanned code
        if (code.startsWith('http')) {
            // If it's a URL, navigate to it
            window.location.href = code;
        } else {
            // Otherwise, show result
            alert('Scanned: ' + code);
        }
    }
}

// Generate QR Code
function generateQRCode(elementId, text, size = 200) {
    const qrcode = new QRCode(document.getElementById(elementId), {
        text: text,
        width: size,
        height: size,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
}

// Copy text to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Web Share API
function shareContent(title, text, url) {
    if (navigator.share) {
        navigator.share({
            title: title,
            text: text,
            url: url
        }).then(() => {
            console.log('Shared successfully');
        }).catch(error => {
            console.log('Error sharing:', error);
        });
    } else {
        // Fallback: Copy to clipboard
        copyToClipboard(url);
    }
}

// Export for use in other files
window.QRScanner = QRScanner;
window.generateQRCode = generateQRCode;
window.copyToClipboard = copyToClipboard;
window.shareContent = shareContent;