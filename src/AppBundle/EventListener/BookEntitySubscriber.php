<?php

namespace AppBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use BookBundle\Entity\Book;

class BookEntitySubscriber implements EventSubscriber
{
    private $storageDirectory;

    public function __construct($storageDirectory)
    {
        $this->storageDirectory = $storageDirectory;
    }

    public function getSubscribedEvents()
    {
        return ['postRemove'];
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $this->deleteBookData($args);
    }

    # удаление связанных файлов при удалении книги
    public function deleteBookData(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Book) {
            # удаляем файл с обложкой
            $coverPath = $entity->getCoverPath();
            if ($coverPath) {
                unlink($this->storageDirectory . $coverPath);
            }

            # удаляем файл с книгой
            $contentPath = $entity->getContentPath();
            if ($contentPath) {
                unlink($this->storageDirectory . $contentPath);
            }
        }
    }
}
