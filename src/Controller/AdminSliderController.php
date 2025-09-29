<?php

namespace App\Controller;

use App\Entity\Slider;
use App\Repository\SliderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/banner')]
#[IsGranted('ROLE_ADMIN')]
class AdminSliderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SliderRepository $sliderRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Display list of all banners
     */
    #[Route('/', name: 'app_admin_banner_index')]
    public function index(): Response
    {
        /** @var \App\Entity\Admin $admin */
        $admin = $this->getUser();
        
        $sliders = $this->sliderRepository->findAllOrdered();
        $activeCount = $this->sliderRepository->countActiveSliders();
        
        return $this->render('admin/banner/index.html.twig', [
            'admin' => $admin,
            'sliders' => $sliders,
            'active_count' => $activeCount,
            'total_count' => count($sliders)
        ]);
    }

    /**
     * Show form to create new banner
     */
    #[Route('/new', name: 'app_admin_banner_new')]
    public function new(): Response
    {
        /** @var \App\Entity\Admin $admin */
        $admin = $this->getUser();
        
        $nextOrder = $this->sliderRepository->getNextOrderNo();
        
        return $this->render('admin/banner/form.html.twig', [
            'admin' => $admin,
            'slider' => null,
            'next_order' => $nextOrder,
            'form_title' => 'Tambah Banner Baru',
            'form_action' => $this->generateUrl('app_admin_banner_create')
        ]);
    }

    /**
     * Handle create banner
     */
    #[Route('/create', name: 'app_admin_banner_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('image');
            
            if (!$imageFile) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Gambar banner wajib diupload'
                ]);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Format file harus JPG atau PNG'
                ]);
            }

            // Validate file size (max 5MB)
            if ($imageFile->getSize() > 5 * 1024 * 1024) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '❌ Ukuran file maksimal 5MB'
                ]);
            }

            $slider = new Slider();
            $slider->setTitle($request->request->get('title'));
            $slider->setDescription($request->request->get('description'));
            $slider->setLink($request->request->get('link'));
            $slider->setOrderNo((int)$request->request->get('order_no', 0));
            $slider->setStatus($request->request->get('status', 'aktif'));

            // Handle file upload
            $fileName = $this->handleFileUpload($imageFile);
            $slider->setImagePath($fileName);

            $this->sliderRepository->save($slider, true);

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Banner berhasil ditambahkan',
                'redirect' => $this->generateUrl('app_admin_banner_index')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menambahkan banner: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show edit form for banner
     */
    #[Route('/{id}/edit', name: 'app_admin_banner_edit')]
    public function edit(Slider $slider): Response
    {
        /** @var \App\Entity\Admin $admin */
        $admin = $this->getUser();
        
        return $this->render('admin/banner/form.html.twig', [
            'admin' => $admin,
            'slider' => $slider,
            'next_order' => null,
            'form_title' => 'Edit Banner',
            'form_action' => $this->generateUrl('app_admin_banner_update', ['id' => $slider->getId()])
        ]);
    }

    /**
     * Handle update banner
     */
    #[Route('/{id}/update', name: 'app_admin_banner_update', methods: ['POST'])]
    public function update(Slider $slider, Request $request): JsonResponse
    {
        try {
            $slider->setTitle($request->request->get('title'));
            $slider->setDescription($request->request->get('description'));
            $slider->setLink($request->request->get('link'));
            $slider->setOrderNo((int)$request->request->get('order_no', 0));
            $slider->setStatus($request->request->get('status', 'aktif'));

            // Handle file upload if new image provided
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                // Validate file
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => '❌ Format file harus JPG atau PNG'
                    ]);
                }

                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => '❌ Ukuran file maksimal 5MB'
                    ]);
                }

                // Delete old image
                $this->deleteOldImage($slider->getImagePath());

                // Upload new image
                $fileName = $this->handleFileUpload($imageFile);
                $slider->setImagePath($fileName);
            }

            $this->sliderRepository->save($slider, true);

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Banner berhasil diupdate',
                'redirect' => $this->generateUrl('app_admin_banner_index')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mengupdate banner: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle status banner (aktif/nonaktif)
     */
    #[Route('/{id}/toggle-status', name: 'app_admin_banner_toggle_status', methods: ['POST'])]
    public function toggleStatus(Slider $slider): JsonResponse
    {
        try {
            $newStatus = $slider->getStatus() === 'aktif' ? 'nonaktif' : 'aktif';
            $slider->setStatus($newStatus);
            
            $this->sliderRepository->save($slider, true);

            return new JsonResponse([
                'success' => true,
                'message' => "✅ Status banner berubah menjadi {$newStatus}",
                'new_status' => $newStatus
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal mengubah status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete banner
     */
    #[Route('/{id}/delete', name: 'app_admin_banner_delete', methods: ['DELETE'])]
    public function delete(Slider $slider): JsonResponse
    {
        try {
            // Delete image file
            $this->deleteOldImage($slider->getImagePath());
            
            // Delete from database
            $this->sliderRepository->remove($slider, true);
            
            // Reorder remaining sliders
            $this->sliderRepository->reorderSliders();

            return new JsonResponse([
                'success' => true,
                'message' => '✅ Banner berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Gagal menghapus banner: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle file upload
     */
    private function handleFileUpload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/sliders';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file->move($uploadDir, $fileName);
            
            return $fileName;
            
        } catch (FileException $e) {
            throw new \Exception('Gagal mengupload file: ' . $e->getMessage());
        }
    }

    /**
     * Delete old image file
     */
    private function deleteOldImage(string $filename): void
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/sliders/' . $filename;
        
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}