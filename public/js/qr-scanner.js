/**
 * SmartUrgence QR Scanner Module (using Html5Qrcode)
 */
class SmartUrgenceScanner {
    constructor(options = {}) {
        this.containerId = options.containerId || 'qr-reader';
        this.statusId = options.statusId || 'qr-scanner-status';
        this.onScanSuccessCallback = options.onSuccess || null;
        this.scannerInstance = null;
        this.isScanning = false;
    }

    /**
     * Extraire l'ID du patient depuis le texte du QR Code.
     * Exemples pris en charge : "PATIENT-42", "#SU-42", "42", "SU-42"
     */
    static parsePatientId(text) {
        if (!text) return null;
        const cleaned = text.trim();
        // Chercher un motif numérique ou précédé de préfixe
        const match = cleaned.match(/(?:PATIENT-|#?SU-)?(\d+)/i);
        return match ? match[1] : cleaned;
    }

    /**
     * Démarrer le scanner vidéo
     */
    start() {
        const container = document.getElementById(this.containerId);
        const statusEl = document.getElementById(this.statusId);
        if (!container) return;

        if (typeof Html5Qrcode === 'undefined') {
            if (statusEl) {
                statusEl.innerHTML = `<div class="alert-banner alert-banner-danger" style="margin-top:0.5rem; padding:0.5rem 0.75rem;">
                    <strong>Erreur :</strong> La bibliothèque de scanner QR code (html5-qrcode) n'est pas chargée.
                </div>`;
            }
            return;
        }

        container.style.display = 'block';
        if (statusEl) {
            statusEl.innerHTML = `<span style="color: var(--primary-600); font-size: 0.85rem;">🎥 Recherche de la caméra... Veuillez autoriser l'accès si demandé.</span>`;
        }

        this.scannerInstance = new Html5Qrcode(this.containerId);
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        this.scannerInstance.start(
            { facingMode: "environment" }, // caméra arrière sur mobile, webcam par défaut sur PC
            config,
            (decodedText, decodedResult) => {
                const patientId = SmartUrgenceScanner.parsePatientId(decodedText);
                if (statusEl) {
                    statusEl.innerHTML = `<span style="color: var(--success-700); font-weight: 700;">✅ QR Code scanné avec succès : Patient #${patientId}</span>`;
                }
                
                // Fermer la caméra immédiatement après détection
                this.stop();

                if (typeof this.onScanSuccessCallback === 'function') {
                    this.onScanSuccessCallback(patientId, decodedText);
                }
            },
            (errorMessage) => {
                // Erreur de cadrage/lecture courante, ne pas polluer l'interface
            }
        ).catch((err) => {
            console.warn("Erreur démarrage caméra:", err);
            this.isScanning = false;
            container.style.display = 'none';
            if (statusEl) {
                statusEl.innerHTML = `<div style="background-color: #FEF2F2; color: #991B1B; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid #FCA5A5; font-size: 0.85rem; margin-top: 0.5rem;">
                    ⚠️ <strong>Accès caméra impossible :</strong> ${err.message || 'Permission refusée ou aucune caméra détectée'}.<br>
                    Veuillez utiliser la <strong>saisie manuelle de secours</strong> ci-dessous.
                </div>`;
            }
        });

        this.isScanning = true;
    }

    /**
     * Arrêter le scanner vidéo
     */
    stop() {
        const container = document.getElementById(this.containerId);
        if (this.scannerInstance && this.isScanning) {
            this.scannerInstance.stop().then(() => {
                this.scannerInstance.clear();
                this.isScanning = false;
                if (container) container.style.display = 'none';
            }).catch(err => console.error("Erreur arrêt scanner:", err));
        } else {
            if (container) container.style.display = 'none';
            this.isScanning = false;
        }
    }

    /**
     * Basculer l'état du scanner (Toggle)
     */
    toggle() {
        if (this.isScanning) {
            this.stop();
        } else {
            this.start();
        }
    }
}
