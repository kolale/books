<?php

namespace BookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BookBundle\Entity\Book;
use BookBundle\Form\BookType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="list")
     */
    public function listAction()
    {
        $manager = $this
            ->getDoctrine()
            ->getManager();

        $books = $manager
            ->createQueryBuilder()
            ->select('b')
            ->from('BookBundle:Book', 'b')
            ->addOrderBy('b.readDate')
            ->getQuery()
            ->useResultCache(true, 86400, 'book_list_result')
            ->getResult();

        $response = $this->render(
            'BookBundle:Default:list.html.twig',
            array('books' => $books)
        );

        return $response;
    }

    # возвращает размер файла (в байтах)
    private function getFileSize($dir, $filename)
    {
        if ($filename) {
            $file = new File($dir . $filename);
            $filesize = $file->getSize();
        } else {
            $filesize = 0;
        }

        return $filesize;
    }

    /**
     * @Route("/edit/{id}", name="edit", requirements={"id": "\d+"})
     */
    public function editAction(Request $request, $id)
    {
        $manager = $this->getDoctrine()->getManager();

        # если $id не 0, то это редактирование существующего элемента
        if ($id) {
            $book = $manager->getRepository('BookBundle:Book')->find($id);
        } else {
            # иначе это создание нового элемента
            $book = new Book();
        }

        # директория для хранения файлов
        $storageDirectory = $this->getParameter('storage_directory');

        # получаем файл с обложкой (если он ранее был загружен)
        $coverPath = $book->getCoverPath();
        $coverFilesize = $this->getFileSize($storageDirectory, $coverPath);

        # получаем файл с книгой (если он ранее был загружен)
        $contentPath = $book->getContentPath();
        $contentFilesize = $this->getFileSize($storageDirectory, $contentPath);

        $editForm = $this->createForm(new BookType(), $book);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $book = $editForm->getData();

            # работаем с файлом обложки
            $book->processFile($book->getCoverData(), $storageDirectory, $coverPath, 'cover');

            # работаем с файлом книги
            $book->processFile($book->getContentData(), $storageDirectory, $contentPath, 'book');

            $manager->persist($book);
            $manager->flush();

            # чистим кэш результатов запроса для списка книг
            $manager->getConfiguration()->getResultCacheImpl()->delete('book_list_result');

            return $this->redirectToRoute('list');
        }

        return
            $this->render(
                'BookBundle:Default:edit.html.twig',
                [
                    'form' => $editForm->createView(),
                    'entity' => $book,
                    'extData' => ['coverFilesize' => $coverFilesize, 'contentFilesize' => $contentFilesize]
                ]
            );
    }

    /**
     * @Route("/delete/{id}", name="delete", requirements={"id": "\d+"})
     */
    public function deleteAction($id)
    {
        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        $manager->remove($book);
        $manager->flush();

        # чистим кэш результатов запроса для списка книг
        $manager->getConfiguration()->getResultCacheImpl()->delete('book_list_result');

        return $this->redirectToRoute('list');
    }

    /**
     * @Route("/delete_cover/{id}", name="delete_cover", requirements={"id": "\d+"})
     */
    public function deleteCoverAction($id)
    {
        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        $coverPath = $book->getCoverPath();

        if ($coverPath) {
            # удаляем старый файл с обложкой
            unlink($this->getParameter('storage_directory') . $coverPath);

            $book->setCoverPath(null);
            $book->setCoverSrcPath(null);

            $manager->persist($book);
            $manager->flush();

            # чистим кэш результатов запроса для списка книг
            $manager->getConfiguration()->getResultCacheImpl()->delete('book_list_result');
        }

        return $this->redirectToRoute('edit', ['id' => $id]);
    }

    /**
     * @Route("/delete_content/{id}", name="delete_content", requirements={"id": "\d+"})
     */
    public function deleteContentAction($id)
    {
        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        $contentPath = $book->getContentPath();

        if ($contentPath) {
            # удаляем старый файл с книгой
            unlink($this->getParameter('storage_directory') . $contentPath);

            $book->setContentPath(null);
            $book->setContentSrcPath(null);

            $manager->persist($book);
            $manager->flush();

            # чистим кэш результатов запроса для списка книг
            $manager->getConfiguration()->getResultCacheImpl()->delete('book_list_result');
        }

        return $this->redirectToRoute('edit', ['id' => $id]);
    }

    /**
     * @Route("/download/{id}", name="download", requirements={"id": "\d+"})
     */
    public function downloadAction(Request $request, $id)
    {
        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        if (!$id) {
            return new Response("Не найдена книга для скачивания с id = $id");
        }

        $response = new BinaryFileResponse($this->getParameter('storage_directory') . $book->getContentPath());

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $book->getContentSrcPath()
        );

        return $response;
    }
}
