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
use JMS\Serializer\SerializerBuilder;

class DefaultController extends Controller
{
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

        if ($book->getCoverPath()) {
            $book->setCoverData(new File($this->getParameter('storage_directory') . $book->getCoverPath()));
        }

        $editForm = $this->createForm(new BookType(), $book);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $book = $editForm->getData();

            $file = $book->getCoverData();

            if ($file) {
                # удаляем старый файл с обложкой
                $cover_path = $book->getCoverPath();
                if ($cover_path) {
                    unlink($this->getParameter('storage_directory') . $cover_path);
                }

                $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                $file->move($this->getParameter('storage_directory'), $fileName);
                $src_filename = $file->getClientOriginalName();

                $book->setCoverPath($fileName);
                $book->setCoverSrcPath($src_filename);
            }

            $manager->persist($book);
            $manager->flush();

            return $this->redirectToRoute('index');
        }

        return
            $this->render(
                'BookBundle:Default:edit.html.twig',
                ['form' => $editForm->createView(), 'entity' => $book]
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

        $response_text =
            $is_changed ? 'Изменения выполнены.' . '<br><br>' . $book_ext_data : 'Нет данных для изменения.';

        return new Response($response_text);
    }
}
