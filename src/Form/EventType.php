<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\UnitKerja;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $unitChoices = $options['unit_choices'] ?? [];
        $builder
            ->add('judulEvent', TextType::class, [
                'label' => 'Judul Event',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500',
                    'placeholder' => 'Masukkan judul event'
                ],
                'constraints' => [
                    new NotBlank(message: 'Judul event wajib diisi'),
                    new Length(
                        max: 150,
                        maxMessage: 'Judul event tidak boleh lebih dari {{ limit }} karakter'
                    )
                ]
            ])
            ->add('deskripsi', TextareaType::class, [
                'label' => 'Deskripsi',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500',
                    'placeholder' => 'Deskripsi event (opsional)',
                    'rows' => 4
                ]
            ])
            ->add('tanggalMulai', DateTimeType::class, [
                'label' => 'Tanggal Mulai',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500',
                    'type' => 'datetime-local'
                ],
                'constraints' => [
                    new NotBlank(message: 'Tanggal mulai wajib diisi')
                ]
            ])
            ->add('tanggalSelesai', DateTimeType::class, [
                'label' => 'Tanggal Selesai',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500',
                    'type' => 'datetime-local'
                ]
            ])
            ->add('lokasi', ChoiceType::class, [
                'label' => 'Lokasi',
                'required' => false,
                'choices' => [
                    'Pilih Lokasi' => '',
                    '📍 Kanwil Kemenag Sulbar' => 'Kanwil Kemenag Sulbar',
                    '🏛️ Aula Kanwil' => 'Aula Kanwil',
                    '🕌 Asrama Haji' => 'Asrama Haji',
                    '💻 Online/Virtual' => 'Online',
                    '📍 Lokasi Lain' => 'custom'
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500',
                    'data-custom-input' => 'lokasi-custom'
                ],
                'help' => 'Pilih lokasi atau pilih "Lokasi Lain" untuk input manual'
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status Event',
                'choices' => [
                    'Aktif' => 'aktif',
                    'Selesai' => 'selesai',
                    'Dibatalkan' => 'dibatalkan'
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500'
                ]
            ])
            ->add('kategoriEvent', ChoiceType::class, [
                'label' => 'Kategori Event',
                'choices' => [
                    '🔵 Kegiatan Kantor' => 'kegiatan_kantor',
                    '🟢 Kegiatan Pusat' => 'kegiatan_pusat',
                    '🟣 Kegiatan Internal' => 'kegiatan_internal',
                    '🟠 Kegiatan External' => 'kegiatan_external'
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500'
                ],
                'help' => 'Pilih kategori yang sesuai untuk event ini'
            ])
            ->add('warna', ColorType::class, [
                'label' => 'Warna Kalender',
                'required' => false,
                'attr' => [
                    'class' => 'w-full h-12 px-2 py-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500'
                ]
            ])
            ->add('butuhAbsensi', CheckboxType::class, [
                'label' => 'Butuh Absensi?',
                'required' => false,
                'attr' => [
                    'class' => 'w-5 h-5 text-sky-600 bg-gray-100 border-gray-300 rounded focus:ring-sky-500 focus:ring-2',
                    'onchange' => 'toggleAbsensiFields(this.checked)'
                ],
                'help' => 'Centang jika event ini membutuhkan absensi dari peserta'
            ])
            ->add('jamMulaiAbsensi', TimeType::class, [
                'label' => 'Jam Mulai Absensi',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 absensi-field',
                    'type' => 'time',
                    'step' => '60'
                ],
                'help' => 'Jam mulai absensi dalam format 24 jam (contoh: 18:00 untuk jam 6 sore)'
            ])
            ->add('jamSelesaiAbsensi', TimeType::class, [
                'label' => 'Jam Selesai Absensi',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 absensi-field',
                    'type' => 'time',
                    'step' => '60'
                ],
                'help' => 'Jam selesai absensi dalam format 24 jam (contoh: 19:00 untuk jam 7 malam)'
            ])
            ->add('linkMeeting', UrlType::class, [
                'label' => 'Link Meeting (Opsional)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500',
                    'placeholder' => 'https://zoom.us/j/... atau https://meet.google.com/...'
                ],
                'help' => 'Link untuk meeting online (Zoom, Google Meet, Teams, dll.)',
                'constraints' => [
                    new Length(
                        max: 500,
                        maxMessage: 'Link meeting tidak boleh lebih dari {{ limit }} karakter'
                    )
                ]
            ])
            ->add('targetAudience', ChoiceType::class, [
                'label' => 'Target Audience',
                'choices' => [
                    'Semua Unit Kerja' => 'all',
                    'Unit Kerja Tertentu' => 'custom'
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => [
                    'class' => 'target-audience-radio'
                ],
                'help' => 'Pilih siapa yang akan menerima notifikasi event ini'
            ])
            ->add('targetUnits', ChoiceType::class, [
                'label' => 'Pilih Unit Kerja',
                'choices' => $unitChoices,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'attr' => [
                    'class' => 'target-units-checkbox'
                ],
                'help' => 'Pilih unit kerja yang akan menerima notifikasi (hanya berlaku jika memilih "Unit Kerja Tertentu")'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'unit_choices' => [],
            'constraints' => [
                new Callback([$this, 'validateDateRange'])
            ]
        ]);
        
        $resolver->setAllowedTypes('unit_choices', 'array');
    }

    public function validateDateRange($data, ExecutionContextInterface $context)
    {
        if ($data instanceof Event) {
            $tanggalMulai = $data->getTanggalMulai();
            $tanggalSelesai = $data->getTanggalSelesai();

            if ($tanggalMulai && $tanggalSelesai && $tanggalMulai > $tanggalSelesai) {
                $context->buildViolation('Tanggal mulai tidak boleh lebih dari tanggal selesai')
                    ->atPath('tanggalSelesai')
                    ->addViolation();
            }

            // Validasi jam absensi jika butuh absensi
            if ($data->isButuhAbsensi()) {
                $jamMulai = $data->getJamMulaiAbsensi();
                $jamSelesai = $data->getJamSelesaiAbsensi();

                if (!$jamMulai) {
                    $context->buildViolation('Jam mulai absensi wajib diisi jika event membutuhkan absensi')
                        ->atPath('jamMulaiAbsensi')
                        ->addViolation();
                }

                if (!$jamSelesai) {
                    $context->buildViolation('Jam selesai absensi wajib diisi jika event membutuhkan absensi')
                        ->atPath('jamSelesaiAbsensi')
                        ->addViolation();
                }

                if ($jamMulai && $jamSelesai && $jamMulai >= $jamSelesai) {
                    $context->buildViolation('Jam mulai absensi harus lebih awal dari jam selesai absensi')
                        ->atPath('jamSelesaiAbsensi')
                        ->addViolation();
                }
            }
        }
    }
}