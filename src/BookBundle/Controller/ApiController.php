<?php

namespace BookBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BookBundle\Entity\Book;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use JMS\Serializer\SerializerBuilder;

class ApiController extends Controller
{
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
        $checkApi = $this->checkApiRequest($request);
        if ($checkApi) {
            return new Response($checkApi);
        }

        $manager = $this->getDoctrine()->getManager();
        $books = $manager->getRepository('BookBundle:Book')->findAll();

        return new Response($this->serialize($books));
    }

    /**
     * @Route("/api/v1/books/{id}/edit", name="api_books_edit", requirements={"id": "\d+"}, methods="POST")
     */
    public function apiBooksEditAction(Request $request, $id)
    {
        # формат данных: book_data={"title":"Тёмная башня", "author":"Стивен Кинг", "read_date":"2017-01-01"}

        $checkApi = $this->checkApiRequest($request);
        if ($checkApi) {
            return new Response($checkApi);
        }

        $manager = $this->getDoctrine()->getManager();
        $book = $manager->getRepository('BookBundle:Book')->find($id);

        if (!$book) {
            return new Response('Книга с указанным id не найдена.');
        }

        /*
        # для тестов
        $bookExtData = $request->getRealMethod() == 'GET' ?
            $request->query->get('book_data') : $request->request->get('book_data');
        */

        $bookExtData = $request->request->get('book_data');
        if (!$bookExtData) {
            return new Response('Не задан JSON-параметр book_data с данными для изменения книги.');
        }
        $serializer = SerializerBuilder::create()->build();
        $bookExt = $serializer->deserialize($bookExtData, 'BookBundle\Entity\Book', 'json');

        # если дошли сюда без exception, значит json валидный и подходит для entity Book

        # проверяем, какие поля были переданы для изменения
        $isChanged = false;

        # проверяем поле title
        $bookExtTitle = $bookExt->getTitle();
        if (!is_null($bookExtTitle)) {
            $book->setTitle($bookExtTitle);
            $isChanged = true;
        }

        # проверяем поле author
        $bookExtAuthor = $bookExt->getAuthor();
        if (!is_null($bookExtAuthor)) {
            $book->setAuthor($bookExtAuthor);
            $isChanged = true;
        }

        # проверяем поле readDate
        $bookExtReadDate = $bookExt->getReadDate();
        if (!is_null($bookExtReadDate)) {
            $book->setReadDate($bookExtReadDate);
            $isChanged = true;
        }

        # проверяем поле downloadEnabled
        $bookExtDownloadEnabled = $bookExt->getDownloadEnabled();
        if (!is_null($bookExtDownloadEnabled)) {
            $book->setDownloadEnabled($bookExtDownloadEnabled);
            $isChanged = true;
        }

        $manager->persist($book);
        $manager->flush();

        # чистим кэш результатов запроса для списка книг
        $manager->getConfiguration()->getResultCacheImpl()->delete('book_list_result');

        $responseText = $isChanged ?
            'Изменения выполнены.' . '<br><br>' . $bookExtData : 'Нет данных для изменения.';

        return new Response($responseText);
    }

    /**
     * @Route("/api/v1/books/add", name="api_books_add", methods="POST"))
     */
    public function apiBooksAddAction(Request $request)
    {
        # формат данных: book_data={"title":"Тёмная башня", "author":"Стивен Кинг", "read_date":"2017-01-01"}

        $checkApi = $this->checkApiRequest($request);
        if ($checkApi) {
            return new Response($checkApi);
        }

        /*
        # для тестов
        $bookExtData = $request->getRealMethod() == 'GET' ?
            $request->query->get('book_data') : $request->request->get('book_data');
        */

        $bookExtData = $request->request->get('book_data');
        if (!$bookExtData) {
            return new Response('Не задан JSON-параметр book_data с данными для добавления книги.');
        }
        $serializer = SerializerBuilder::create()->build();
        $book = $serializer->deserialize($bookExtData, 'BookBundle\Entity\Book', 'json');

        if (!$book->getTitle()) {
            return new Response('Не задано наименование книги (JSON-параметр title).');
        }

        if (!$book->getAuthor()) {
            return new Response('Не задан автор книги (JSON-параметр author).');
        }

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($book);
        $manager->flush();

        # чистим кэш результатов запроса для списка книг
        $manager->getConfiguration()->getResultCacheImpl()->delete('book_list_result');

        return new Response('Добавление выполнено.' . '<br><br>' . 'id = ' . $book->getId());
    }
}
