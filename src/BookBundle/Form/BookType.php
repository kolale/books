<?php

namespace BookBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', 'text', ['label' => 'Название книги: '])
            ->add('author', 'text', ['label' => 'Автор: '])
            ->add('readDate', 'date', ['label' => 'Дата прочтения: ', 'required' => false])
            ->add('downloadEnabled', 'checkbox', ['label' => 'Скачивание разрешено: ', 'required' => false])
            ->add('coverData', 'file', ['label' => 'Обложка: ', 'required' => false, 'error_bubbling' => true])
            ->add(
                'coverPath',
                'text',
                [
                    'label' => 'Сохранённый файл обложки: ',
                    'required' => false,
                    'attr' => ['size' => '100%'],
                    'disabled' => true
                ]
            )
            ->add(
                'coverSrcPath',
                'text',
                [
                    'label' => 'Реальный файл обложки: ',
                    'required' => false,
                    'attr' => ['size' => '100%'],
                    'disabled' => true
                ]
            )
            ->add('contentData', 'file', ['label' => 'Книга: ', 'required' => false, 'error_bubbling' => true])
            ->add(
                'contentPath',
                'text',
                [
                    'label' => 'Сохранённый файл книги: ',
                    'required' => false,
                    'attr' => ['size' => '100%'],
                    'disabled' => true
                ]
            )
            ->add(
                'contentSrcPath',
                'text',
                [
                    'label' => 'Реальный файл книги: ',
                    'required' => false,
                    'attr' => ['size' => '100%'],
                    'disabled' => true
                ]
            )
            ->add('Сохранить', 'submit');
    }

    public function getName()
    {
        return 'book';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => 'BookBundle\Entity\Book']);
    }
}
