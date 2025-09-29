#!/usr/bin/env python3
"""
QR Code Repair and Analysis Tool
Untuk menganalisis dan memperbaiki QR code yang rusak
"""

import cv2
import numpy as np
import qrcode
from qrcode.constants import ERROR_CORRECT_L, ERROR_CORRECT_M, ERROR_CORRECT_Q, ERROR_CORRECT_H
from pyzbar import pyzbar
import argparse
import os
from PIL import Image, ImageEnhance
import re

class QRRepairTool:
    def __init__(self):
        self.error_levels = {
            'L': ERROR_CORRECT_L,  # ~7% recovery
            'M': ERROR_CORRECT_M,  # ~15% recovery  
            'Q': ERROR_CORRECT_Q,  # ~25% recovery
            'H': ERROR_CORRECT_H   # ~30% recovery
        }
    
    def analyze_qr_structure(self, image_path):
        """Analisis struktur QR code untuk deteksi kerusakan"""
        print("🔍 Menganalisis struktur QR code...")
        
        try:
            # Baca gambar
            img = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
            if img is None:
                return {"error": "Tidak dapat membaca file gambar"}
            
            print(f"📏 Dimensi gambar: {img.shape[1]}x{img.shape[0]}")
            
            # Deteksi finder patterns (3 kotak sudut)
            finder_patterns = self._detect_finder_patterns(img)
            
            # Analisis kualitas gambar
            blur_score = cv2.Laplacian(img, cv2.CV_64F).var()
            
            # Coba decode QR code
            decoded = pyzbar.decode(img)
            
            analysis = {
                "image_size": img.shape,
                "finder_patterns_detected": len(finder_patterns),
                "blur_score": blur_score,
                "is_readable": len(decoded) > 0,
                "decoded_data": decoded[0].data.decode() if decoded else None,
                "finder_patterns": finder_patterns
            }
            
            return analysis
            
        except Exception as e:
            return {"error": f"Error analisis: {str(e)}"}
    
    def _detect_finder_patterns(self, img):
        """Deteksi finder patterns (kotak sudut QR)"""
        # Threshold gambar
        _, thresh = cv2.threshold(img, 127, 255, cv2.THRESH_BINARY)
        
        # Cari kontur
        contours, _ = cv2.findContours(thresh, cv2.RETR_TREE, cv2.CHAIN_APPROX_SIMPLE)
        
        finder_patterns = []
        for contour in contours:
            # Aproksimasi kontur ke polygon
            epsilon = 0.02 * cv2.arcLength(contour, True)
            approx = cv2.approxPolyDP(contour, epsilon, True)
            
            # Cek apakah bentuknya persegi
            if len(approx) == 4:
                x, y, w, h = cv2.boundingRect(contour)
                aspect_ratio = w / h
                
                # Filter berdasarkan rasio aspek dan ukuran
                if 0.8 <= aspect_ratio <= 1.2 and w > 20 and h > 20:
                    finder_patterns.append({
                        "position": (x, y),
                        "size": (w, h),
                        "area": cv2.contourArea(contour)
                    })
        
        return finder_patterns
    
    def enhance_image(self, image_path, output_path=None):
        """Perbaiki kualitas gambar QR code"""
        print("✨ Meningkatkan kualitas gambar...")
        
        try:
            # Buka dengan PIL untuk enhancement
            pil_img = Image.open(image_path)
            
            # Convert ke grayscale
            if pil_img.mode != 'L':
                pil_img = pil_img.convert('L')
            
            # Tingkatkan kontras
            enhancer = ImageEnhance.Contrast(pil_img)
            pil_img = enhancer.enhance(2.0)
            
            # Tingkatkan sharpness
            enhancer = ImageEnhance.Sharpness(pil_img)
            pil_img = enhancer.enhance(2.0)
            
            # Convert ke OpenCV
            cv_img = cv2.cvtArray(np.array(pil_img))
            
            # Denoising
            cv_img = cv2.fastNlMeansDenoising(cv_img)
            
            # Adaptive threshold untuk kontras lebih baik
            thresh = cv2.adaptiveThreshold(cv_img, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, 
                                         cv2.THRESH_BINARY, 11, 2)
            
            # Simpan hasil enhancement
            if output_path:
                cv2.imwrite(output_path, thresh)
                print(f"💾 Gambar yang diperbaiki disimpan: {output_path}")
            
            return thresh
            
        except Exception as e:
            print(f"❌ Error enhancement: {str(e)}")
            return None
    
    def try_decode_variants(self, image_path):
        """Coba decode dengan berbagai metode preprocessing"""
        print("🔄 Mencoba berbagai metode decode...")
        
        # Baca gambar original
        original = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
        if original is None:
            return None
        
        variants = [
            ("Original", original),
            ("Binary Threshold", cv2.threshold(original, 127, 255, cv2.THRESH_BINARY)[1]),
            ("Adaptive Threshold", cv2.adaptiveThreshold(original, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 11, 2)),
            ("OTSU Threshold", cv2.threshold(original, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)[1])
        ]
        
        # Juga coba rotasi jika perlu
        for angle in [0, 90, 180, 270]:
            if angle > 0:
                rows, cols = original.shape
                M = cv2.getRotationMatrix2D((cols/2, rows/2), angle, 1)
                rotated = cv2.warpAffine(original, M, (cols, rows))
                variants.append((f"Rotated {angle}°", rotated))
        
        for name, variant in variants:
            try:
                decoded = pyzbar.decode(variant)
                if decoded:
                    data = decoded[0].data.decode()
                    print(f"✅ Berhasil decode dengan metode: {name}")
                    print(f"📝 Data: {data}")
                    return data
            except:
                continue
        
        print("❌ Tidak berhasil decode dengan semua metode")
        return None
    
    def reconstruct_qr(self, data, error_level='H', output_path=None):
        """Buat ulang QR code dengan data yang sudah didecode"""
        print(f"🔨 Membuat ulang QR code dengan error correction level {error_level}...")
        
        try:
            # Deteksi jenis data untuk optimasi
            version = self._determine_qr_version(data)
            
            qr = qrcode.QRCode(
                version=version,
                error_correction=self.error_levels[error_level],
                box_size=10,
                border=4,
            )
            
            qr.add_data(data)
            qr.make(fit=True)
            
            # Buat gambar QR
            qr_img = qr.make_image(fill_color="black", back_color="white")
            
            # Simpan jika diminta
            if output_path:
                qr_img.save(output_path)
                print(f"💾 QR code baru disimpan: {output_path}")
            
            return qr_img
            
        except Exception as e:
            print(f"❌ Error membuat QR code: {str(e)}")
            return None
    
    def _determine_qr_version(self, data):
        """Tentukan versi QR yang optimal berdasarkan panjang data"""
        data_len = len(data)
        
        # Kapasitas data untuk berbagai versi (alphanumeric)
        capacities = [25, 47, 77, 114, 154, 195, 224, 279, 335]
        
        for i, capacity in enumerate(capacities):
            if data_len <= capacity:
                return i + 1
        
        return None  # Auto-determine
    
    def extract_partial_data(self, image_path):
        """Ekstrak data parsial dari QR code yang rusak"""
        print("🔍 Mencoba ekstrak data parsial...")
        
        # Implementasi advanced untuk ekstrak data parsial
        # Ini memerlukan library khusus seperti libdmtx atau implementasi custom
        
        # Untuk sekarang, coba dengan berbagai preprocessing
        partial_data = self.try_decode_variants(image_path)
        
        if partial_data:
            return partial_data
        
        # Jika masih gagal, coba analisis manual pattern
        img = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
        if img is not None:
            # Analisis format information bits
            # Ekstrak data modules yang masih readable
            # Ini adalah implementasi lanjutan yang memerlukan pengetahuan detail QR spec
            pass
        
        return None

def main():
    parser = argparse.ArgumentParser(description='QR Code Repair Tool')
    parser.add_argument('input', help='Path ke file QR code yang rusak')
    parser.add_argument('--output', '-o', help='Path output untuk QR code yang diperbaiki')
    parser.add_argument('--error-level', '-e', choices=['L', 'M', 'Q', 'H'], 
                       default='H', help='Error correction level (default: H)')
    parser.add_argument('--enhance', '-n', action='store_true', 
                       help='Enhance gambar sebelum decode')
    
    args = parser.parse_args()
    
    if not os.path.exists(args.input):
        print(f"❌ File tidak ditemukan: {args.input}")
        return
    
    tool = QRRepairTool()
    
    print(f"🚀 Memulai analisis QR code: {args.input}")
    print("=" * 50)
    
    # 1. Analisis struktur
    analysis = tool.analyze_qr_structure(args.input)
    print("\n📊 HASIL ANALISIS:")
    print("-" * 30)
    
    if "error" in analysis:
        print(f"❌ {analysis['error']}")
        return
    
    for key, value in analysis.items():
        print(f"{key}: {value}")
    
    # 2. Enhancement jika diminta
    enhanced_path = None
    if args.enhance:
        enhanced_path = args.input.replace('.', '_enhanced.')
        tool.enhance_image(args.input, enhanced_path)
        decode_target = enhanced_path
    else:
        decode_target = args.input
    
    # 3. Coba decode
    print(f"\n🔄 PROSES DECODE:")
    print("-" * 30)
    
    decoded_data = tool.try_decode_variants(decode_target)
    
    # 4. Rekonstruksi jika berhasil decode
    if decoded_data:
        print(f"\n🔨 REKONSTRUKSI QR CODE:")
        print("-" * 30)
        
        output_path = args.output or args.input.replace('.', '_repaired.')
        tool.reconstruct_qr(decoded_data, args.error_level, output_path)
        
        print(f"\n✅ SELESAI! QR code diperbaiki dan disimpan di: {output_path}")
    else:
        print(f"\n❌ Tidak dapat decode QR code. Kemungkinan kerusakan terlalu parah.")
        print("💡 Saran:")
        print("   - Coba scan ulang dengan kualitas lebih baik")
        print("   - Gunakan --enhance flag")
        print("   - Bersihkan QR code dari kotoran/goresan")

# Script untuk keperluan web (tanpa argparse)
def repair_qr_web(image_path, output_path=None):
    """Function untuk digunakan dari web application"""
    tool = QRRepairTool()
    
    # Analisis
    analysis = tool.analyze_qr_structure(image_path)
    if "error" in analysis:
        return {"success": False, "error": analysis["error"]}
    
    # Enhancement
    enhanced_path = image_path.replace('.', '_enhanced.')
    tool.enhance_image(image_path, enhanced_path)
    
    # Decode
    decoded_data = tool.try_decode_variants(enhanced_path)
    
    if decoded_data:
        # Rekonstruksi
        if not output_path:
            output_path = image_path.replace('.', '_repaired.')
        
        qr_img = tool.reconstruct_qr(decoded_data, 'H', output_path)
        
        return {
            "success": True,
            "original_data": decoded_data,
            "repaired_path": output_path,
            "analysis": analysis
        }
    else:
        return {
            "success": False, 
            "error": "Tidak dapat decode QR code",
            "analysis": analysis
        }

if __name__ == "__main__":
    main()