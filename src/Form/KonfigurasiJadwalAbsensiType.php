<?php

namespace App\Form;

use App\Entity\KonfigurasiJadwalAbsensi;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form untuk Admin Mengatur Jadwal Absensi yang Fleksibel
 * 
 * Form ini memungkinkan admin untuk:
 * - Menentukan nama jadwal
 * - Mengatur hari dan jam absensi
 * - Memilih apakah perlu QR code dan kamera
 * - Kustomisasi tampilan (emoji, warna)
 * 
 * @author Indonesian Developer
 */
class KonfigurasiJadwalAbsensiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // === INFORMASI DASAR JADWAL ===
            ->add('namaJadwal', TextType::class, [
                'label' => 'Nama Jadwal Absensi',
                'help' => 'Contoh: Apel Pagi, Rapat Mingguan, Kegiatan Khusus',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Masukkan nama jadwal absensi'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Nama jadwal tidak boleh kosong'
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 100,
                        'minMessage' => 'Nama jadwal minimal 3 karakter',
                        'maxMessage' => 'Nama jadwal maksimal 100 karakter'
                    ])
                ]
            ])

            ->add('deskripsi', TextareaType::class, [
                'label' => 'Deskripsi (Opsional)',
                'required' => false,
                'help' => 'Penjelasan singkat tentang jadwal absensi ini',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Masukkan deskripsi jadwal jika diperlukan'
                ]
            ])

            // === PENGATURAN HARI ===
            ->add('hariMulai', ChoiceType::class, [
                'label' => 'Hari Mulai',
                'help' => 'Pilih hari mulai jadwal absensi',
                'choices' => [
                    'Senin' => 1,
                    'Selasa' => 2,
                    'Rabu' => 3,
                    'Kamis' => 4,
                    'Jumat' => 5,
                    'Sabtu' => 6,
                    'Minggu' => 7,
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Hari mulai harus dipilih'
                    ]),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 7,
                        'notInRangeMessage' => 'Hari tidak valid'
                    ])
                ]
            ])

            ->add('hariSelesai', ChoiceType::class, [
                'label' => 'Hari Selesai',
                'help' => 'Pilih hari selesai jadwal absensi (bisa sama dengan hari mulai)',
                'choices' => [
                    'Senin' => 1,
                    'Selasa' => 2,
                    'Rabu' => 3,
                    'Kamis' => 4,
                    'Jumat' => 5,
                    'Sabtu' => 6,
                    'Minggu' => 7,
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Hari selesai harus dipilih'
                    ]),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 7,
                        'notInRangeMessage' => 'Hari tidak valid'
                    ])
                ]
            ])

            // === PENGATURAN JAM ===
            ->add('jamMulai', TimeType::class, [
                'label' => 'Jam Mulai',
                'help' => 'Waktu mulai absensi dibuka',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'step' => 60 // Presisi per menit
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Jam mulai harus diisi'
                    ])
                ]
            ])

            ->add('jamSelesai', TimeType::class, [
                'label' => 'Jam Selesai',
                'help' => 'Waktu absensi ditutup',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'step' => 60 // Presisi per menit
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Jam selesai harus diisi'
                    ])
                ]
            ])

            // === PENGATURAN QR CODE DAN KAMERA ===
            ->add('perluQrCode', CheckboxType::class, [
                'label' => 'Perlu Scan QR Code?',
                'help' => 'Centang jika absensi memerlukan scan QR code',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])

            ->add('perluKamera', CheckboxType::class, [
                'label' => 'Perlu Foto Kamera?',
                'help' => 'Centang jika absensi memerlukan foto/selfie',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])

            // === KUSTOMISASI TAMPILAN ===
            ->add('emoji', TextType::class, [
                'label' => 'Emoji',
                'help' => 'Emoji yang akan ditampilkan di kartu absensi',
                'required' => false,
                'data' => '✅', // Default emoji
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '✅',
                    'maxlength' => 4
                ]
            ])

            ->add('warnaKartu', ColorType::class, [
                'label' => 'Warna Kartu',
                'help' => 'Warna latar belakang kartu absensi',
                'required' => false,
                'data' => '#3B82F6', // Default biru
                'attr' => [
                    'class' => 'form-control form-control-color'
                ]
            ])

            // === STATUS DAN KETERANGAN ===
            ->add('isAktif', CheckboxType::class, [
                'label' => 'Jadwal Aktif?',
                'help' => 'Centang untuk mengaktifkan jadwal ini',
                'required' => false,
                'data' => true, // Default aktif
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])

            ->add('keterangan', TextareaType::class, [
                'label' => 'Keterangan Tambahan (Opsional)',
                'required' => false,
                'help' => 'Informasi tambahan untuk admin lain',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Catatan internal atau informasi tambahan'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => KonfigurasiJadwalAbsensi::class,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => true
            ]
        ]);
    }

    /**
     * Method untuk mendapatkan pilihan hari dalam bahasa Indonesia
     * (helper method jika diperlukan di tempat lain)
     */
    public static function getPilihanHari(): array
    {
        return [
            'Senin' => 1,
            'Selasa' => 2,
            'Rabu' => 3,
            'Kamis' => 4,
            'Jumat' => 5,
            'Sabtu' => 6,
            'Minggu' => 7,
        ];
    }

    /**
     * Method untuk mendapatkan pilihan emoji populer
     * (bisa digunakan untuk suggest emoji di frontend)
     */
    public static function getEmojiPopuler(): array
    {
        return [
            '✅' => 'Check Mark',
            '🏢' => 'Kantor',
            '📖' => 'Buku/Belajar',
            '🤲' => 'Doa/Ibadah',
            '🇮🇩' => 'Indonesia',
            '📝' => 'Tulis',
            '👥' => 'Rapat',
            '🎯' => 'Target',
            '📊' => 'Meeting',
            '💼' => 'Kerja'
        ];
    }

    /**
     * Method untuk mendapatkan pilihan warna populer
     * (bisa digunakan untuk suggest warna di frontend)
     */
    public static function getWarnaPopuler(): array
    {
        return [
            '#3B82F6' => 'Biru',
            '#10B981' => 'Hijau',
            '#8B5CF6' => 'Ungu',
            '#F59E0B' => 'Kuning',
            '#EF4444' => 'Merah',
            '#6B7280' => 'Abu-abu',
            '#EC4899' => 'Pink',
            '#14B8A6' => 'Teal'
        ];
    }
}