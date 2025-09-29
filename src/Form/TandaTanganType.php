<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

// Form untuk upload tanda tangan pegawai (hanya sekali upload)
class TandaTanganType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tandaTangan', FileType::class, [
                'label' => '📝 Upload Tanda Tangan Digital',
                'mapped' => false, // Tidak langsung di-map ke entity, akan diproses manual
                'required' => true,
                'help' => 'File PNG/SVG, maksimal 100KB. Tanda tangan ini akan digunakan untuk semua absensi.',
                'attr' => [
                    'accept' => '.png,.svg',
                    'class' => 'block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '100k', // Maksimal 100KB sesuai requirement
                        'mimeTypes' => [
                            'image/png',
                            'image/svg+xml',
                        ],
                        'mimeTypesMessage' => 'Upload file tanda tangan dalam format PNG atau SVG',
                        'maxSizeMessage' => 'File tanda tangan terlalu besar. Maksimal 100KB'
                    ])
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => '💾 Simpan Tanda Tangan',
                'attr' => [
                    'class' => 'w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Form ini tidak langsung terikat ke entity karena perlu pemrosesan khusus
            'data_class' => null,
        ]);
    }
}