<?php

namespace AppBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use BookBundle\Entity\Book;

class BookEntitySubscriber implements EventSubscriber
{
    private $storage_directory;

    public function __construct($storage_directory)
    {
        $this->storage_directory = $storage_directory;
    }

    public function getSubscribedEvents()
    {
        return ['postRemove'];
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $this->index($args);
    }

    public function index(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Book) {
            # удаляем файл с обложкой
            $cover_path = $entity->getCoverPath();
            if ($cover_path) {
                unlink($this->storage_directory . $cover_path);
            }

            # удаляем файл с книгой
            $content_path = $entity->getContentPath();
            if ($content_path) {
                unlink($this->storage_directory . $content_path);
            }
        }
    }
}
