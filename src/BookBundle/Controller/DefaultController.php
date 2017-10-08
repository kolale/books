<?php

namespace BookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

use BookBundle\Entity\Book;
use BookBundle\Form\BookType;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use JMS\Serializer\SerializerBuilder;

class DefaultController extends Controller
{
    # максимальное кол-во загруженных файлов в одном каталоге
    const MAX_FILES_CNT_IN_DIR = 5;

    /**
     * @Route("/", name="index")
     */
    public function indexAction()
    {
        $manager = $this->getDoctrine()
                        ->getManager();

        $books = $manager->createQueryBuilder()
                         ->select('b')
                         ->from('BookBundle:Book', 'b')
                         ->addOrderBy('b.readDate')
                         ->getQuery()
                         ->getResult();

        $response = $this->render(
            'BookBundle:Default:index.html.twig',
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

    # записывает переданный файл, устанавливает необходимые свойства
    private function processFile($file, $storage_directory, $old_filename, $entity, $file_type)
    {
        if ($file) {
            # удаляем старый файл с данными
            if ($old_filename) {
                unlink($storage_directory . $old_filename);
            }

            $src_filename = $file->getClientOriginalName();
            $filename = md5(uniqid()) . '.' . $file->guessExtension();

            # ищем каталог в хранилище с кол-вом файлов в нём < MAX_FILES_CNT_IN_DIR
            $valid_dir = null;
            # перебираем все подкаталоги в хранилище
            foreach (glob($storage_directory . '*', GLOB_ONLYDIR) as $dir) {
                $dir_files_cnt = 0;
                # считаем файлы в подкаталогах
                foreach (glob($dir . '/*') as $dir_file) {
                    $dir_files_cnt++;
                }
                if ($dir_files_cnt < self::MAX_FILES_CNT_IN_DIR) {
                    $valid_dir = preg_replace('/^' . str_replace('/', '\/', $storage_directory) . '/', '', $dir);
                    break;
                }
            }

            # $storage_directory - каталог хранилища с завершающим /
            # $valid_dir - подкаталог с файлами
            # $filename - имя файла

            # если в хранилище нет доступных подкаталогов или все переполнены, создаём новый
            if (!$valid_dir) {
                $valid_dir = md5(uniqid());
                mkdir($storage_directory . $valid_dir);
            }

            # в имени файла лежит относительный путь от хранилища (с каталогом)
            $file->move($storage_directory . $valid_dir, $filename);

            if ($file_type == 'cover') {
                $entity->setCoverPath($valid_dir . '/' . $filename);
                $entity->setCoverSrcPath($src_filename);
            } else {
                $entity->setContentPath($valid_dir . '/' . $filename);
                $entity->setContentSrcPath($src_filename);
            }
        }
    }

    /**
     * @Route("/edit/{id}", name="edit", requirements={"id": "\d+"})
     */
    public function editAction(Request $request, $id)
    {
        $manager = $this->getDoctrine()
                        ->getManager();

        # если $id не 0, то это редактирование существующего элемента
        if ($id) {
            $book = $manager->getRepository('BookBundle:Book')->find($id);
        } else {
            # иначе это создание нового элемента
            $book = new Book();
        }

        # директория для хранения файлов
        $storage_directory = $this->getParameter('storage_directory');

        # получаем файл с обложкой (если он ранее был загружен)
        $cover_path = $book->getCoverPath();
        $cover_filesize = $this->getFileSize($storage_directory, $cover_path);

        # получаем файл с книгой (если он ранее был загружен)
        $content_path = $book->getContentPath();
        $content_filesize = $this->getFileSize($storage_directory, $content_path);

        $editForm = $this->createForm(new BookType(), $book);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $book = $editForm->getData();

            # работаем с файлом обложки
            $this->processFile($book->getCoverData(), $storage_directory, $cover_path, $book, 'cover');

            # работаем с файлом книги
            $this->processFile($book->getContentData(), $storage_directory, $content_path, $book, 'book');

            $manager->persist($book);
            $manager->flush();

            return $this->redirectToRoute('index');
        }

        return
            $this->render(
                'BookBundle:Default:edit.html.twig',
                [
                    'form' => $editForm->createView(),
                    'entity' => $book,
                    'ext_data' => ['cover_filesize' => $cover_filesize, 'content_filesize' => $content_filesize]
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

        return $this->redirectToRoute('index');
    }

    /**
     * @Route("/delete_cover/{id}", name="delete_cover", requirements={"id": "\d+"})
     */
    public function deleteCoverAction($id)
    {
        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        $cover_path = $book->getCoverPath();

        if ($cover_path) {
            # удаляем старый файл с обложкой
            unlink($this->getParameter('storage_directory') . $cover_path);

            $book->setCoverPath(null);
            $book->setCoverSrcPath(null);

            $manager->persist($book);
            $manager->flush();
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

        $content_path = $book->getContentPath();

        if ($content_path) {
            # удаляем старый файл с книгой
            unlink($this->getParameter('storage_directory') . $content_path);

            $book->setContentPath(null);
            $book->setContentSrcPath(null);

            $manager->persist($book);
            $manager->flush();
        }

        return $this->redirectToRoute('edit', ['id' => $id]);
    }

    # возвращает сериализованный в JSON объект
    private function serialize($data)
    {
        $serializer = SerializerBuilder::create()->build();
        $jsonContent = $serializer->serialize($data, 'json');

        return $jsonContent;
    }

    # проверяет корректность запроса к api
    private function checkApiRequest(Request $request)
    {
        $receivedApiKey = $request->getRealMethod() == 'GET' ?
            $request->query->get('api_key') : $request->request->get('api_key');
        $realApiKey = $this->getParameter('api_key');

        if (!$receivedApiKey) {
            $error = 'Не задан apiKey.';
        } elseif ($receivedApiKey != $realApiKey) {
            $error = 'apiKey не прошёл проверку.';
        } else {
            $error = null;
        }

        return $error;
    }

    /**
     * @Route("/api/v1/books", name="api_books")
     */
    public function apiBooksAction(Request $request)
    {
        $check_api = $this->checkApiRequest($request);
        if ($check_api) {
            return new Response($check_api);
        }

        $manager = $this->getDoctrine()->getManager();
        $books = $manager->getRepository('BookBundle:Book')->findAll();

        return new Response($this->serialize($books));
    }

    /**
     * @Route("/api/v1/books/{id}/edit", name="api_books_edit", requirements={"id": "\d+"})
     */
    public function apiBooksEditAction(Request $request, $id)
    {
        $check_api = $this->checkApiRequest($request);
        if ($check_api) {
            return new Response($check_api);
        }

        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        if (!$book) {
            return new Response('Книга с указанным id не найдена.');
        }

        $book_ext_data = $request->getRealMethod() == 'GET' ?
            $request->query->get('book_data') : $request->request->get('book_data');
        if (!$book_ext_data) {
            return new Response('Не задан JSON-параметр book_data с данными для изменения книги.');
        }
        $serializer = SerializerBuilder::create()->build();
        $book_ext = $serializer->deserialize($book_ext_data, 'BookBundle\Entity\Book', 'json');

        # если дошли сюда без exception, значит json валидный и подходит для entity Book

        # проверяем, какие поля были переданы для изменения
        $is_changed = false;

        # проверяем поле title
        $book_ext_title = $book_ext->getTitle();
        if (!is_null($book_ext_title)) {
            $book->setTitle($book_ext_title);
            $is_changed = true;
        }

        # проверяем поле author
        $book_ext_author = $book_ext->getAuthor();
        if (!is_null($book_ext_author)) {
            $book->setAuthor($book_ext_author);
            $is_changed = true;
        }

        # проверяем поле read_date
        $book_ext_read_date = $book_ext->getReadDate();
        if (!is_null($book_ext_read_date)) {
            $book->setReadDate($book_ext_read_date);
            $is_changed = true;
        }

        # проверяем поле download_enabled
        $book_ext_download_enabled = $book_ext->getDownloadEnabled();
        if (!is_null($book_ext_download_enabled)) {
            $book->setDownloadEnabled($book_ext_download_enabled);
            $is_changed = true;
        }

        $manager->persist($book);
        $manager->flush();

        $response_text = $is_changed ?
            'Изменения выполнены.' . '<br><br>' . $book_ext_data : 'Нет данных для изменения.';

        return new Response($response_text);
    }

    /**
     * @Route("/api/v1/books/add", name="api_books_add")
     */
    public function apiBooksAddAction(Request $request)
    {
        # добавление по URL вида: http://192.168.1.70:8000/api/v1/books/add?
        # api_key=123456&book_data={"title":"Тёмная башня", "author":"Стивен Кинг", "read_date":"2017-01-01"}

        $check_api = $this->checkApiRequest($request);
        if ($check_api) {
            return new Response($check_api);
        }

        $book_ext_data = $request->getRealMethod() == 'GET' ?
            $request->query->get('book_data') : $request->request->get('book_data');
        if (!$book_ext_data) {
            return new Response('Не задан JSON-параметр book_data с данными для добавления книги.');
        }
        $serializer = SerializerBuilder::create()->build();
        $book = $serializer->deserialize($book_ext_data, 'BookBundle\Entity\Book', 'json');

        if (!$book->getTitle()) {
            return new Response('Не задано наименование книги (JSON-параметр title).');
        }

        if (!$book->getAuthor()) {
            return new Response('Не задан автор книги (JSON-параметр author).');
        }

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($book);
        $manager->flush();

        return new Response('Добавление выполнено.' . '<br><br>' . 'id = ' . $book->getId());
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
