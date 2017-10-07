<?php

namespace AppBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use BookBundle\Entity\Book;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class BookEntitySubscriber implements EventSubscriber
{
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
            $cover_path = $entity->getCoverPath();

            if ($cover_path) {
                # удаляем файл с обложкой
                unlink('/home/kas/devel/books/web/storage/' . $cover_path);
            }
        }
    }
}
