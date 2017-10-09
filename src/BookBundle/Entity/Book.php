<?php

namespace BookBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as JMS;

/**
 * @ORM\Entity
 * @ORM\Table(name="book", indexes={@ORM\Index(name="read_date_idx", columns={"read_date"})})
 * @JMS\ExclusionPolicy("all")
 */
class Book
{
    # максимальное кол-во загруженных файлов в одном каталоге
    const MAX_FILES_CNT_IN_DIR = 5;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="seq_book_id", initialValue=1)
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=1000)
     * @JMS\Expose
     * @JMS\Type("string")
     */
    protected $title;

    /**
     * @ORM\Column(type="string", length=1000)
     * @JMS\Expose
     * @JMS\Type("string")
     */
    protected $author;

    /**
     * @ORM\Column(type="string", length=1000, nullable=true)
     * @JMS\Expose
     * @JMS\Type("string")
     */
    protected $coverPath;

    /**
     * @ORM\Column(type="string", length=1000, nullable=true)
     */
    protected $coverSrcPath;

    /**
     * @Assert\File(maxSize="5M", mimeTypes={"image/png", "image/jpeg", "image/jpg"})
     */
    protected $coverData;

    /**
     * @ORM\Column(type="string", length=1000, nullable=true)
     * @JMS\Expose
     * @JMS\Type("string")
     */
    protected $contentPath;

    /**
     * @ORM\Column(type="string", length=1000, nullable=true)
     */
    protected $contentSrcPath;

    /**
     * @Assert\File(maxSize="5M")
     */
    protected $contentData;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @JMS\Expose
     * @JMS\Type("DateTime<'Y-m-d'>")
     */
    protected $readDate;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @JMS\Expose
     * @JMS\Type("boolean")
     */
    protected $downloadEnabled;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return Book
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set author
     *
     * @param string $author
     * @return Book
     */
    public function setAuthor($author)
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get author
     *
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set coverPath
     *
     * @param string $coverPath
     * @return Book
     */
    public function setCoverPath($coverPath)
    {
        $this->coverPath = $coverPath;

        return $this;
    }

    /**
     * Get coverPath
     *
     * @return string
     */
    public function getCoverPath()
    {
        return $this->coverPath;
    }

    /**
     * Set coverSrcPath
     *
     * @param string $coverSrcPath
     * @return Book
     */
    public function setCoverSrcPath($coverSrcPath)
    {
        $this->coverSrcPath = $coverSrcPath;

        return $this;
    }

    /**
     * Get coverSrcPath
     *
     * @return string
     */
    public function getCoverSrcPath()
    {
        return $this->coverSrcPath;
    }

    /**
     * Set coverData
     *
     * @param File $coverData
     * @return Book
     */
    public function setCoverData($coverData)
    {
        $this->coverData = $coverData;

        return $this;
    }

    /**
     * Get coverData
     *
     * @return File
     */
    public function getCoverData()
    {
        return $this->coverData;
    }

    /**
     * Set contentPath
     *
     * @param string $contentPath
     * @return Book
     */
    public function setContentPath($contentPath)
    {
        $this->contentPath = $contentPath;

        return $this;
    }

    /**
     * Get contentPath
     *
     * @return string
     */
    public function getContentPath()
    {
        return $this->contentPath;
    }

    /**
     * Set contentSrcPath
     *
     * @param string $contentSrcPath
     * @return Book
     */
    public function setContentSrcPath($contentSrcPath)
    {
        $this->contentSrcPath = $contentSrcPath;

        return $this;
    }

    /**
     * Get contentSrcPath
     *
     * @return string
     */
    public function getContentSrcPath()
    {
        return $this->contentSrcPath;
    }

    /**
     * Set contentData
     *
     * @param File $contentData
     * @return Book
     */
    public function setContentData($contentData)
    {
        $this->contentData = $contentData;

        return $this;
    }

    /**
     * Get contentData
     *
     * @return File
     */
    public function getContentData()
    {
        return $this->contentData;
    }

    /**
     * Set readDate
     *
     * @param \DateTime $readDate
     * @return Book
     */
    public function setReadDate($readDate)
    {
        $this->readDate = $readDate;

        return $this;
    }

    /**
     * Get readDate
     *
     * @return \DateTime
     */
    public function getReadDate()
    {
        return $this->readDate;
    }

    /**
     * Set downloadEnabled
     *
     * @param boolean $downloadEnabled
     * @return Book
     */
    public function setDownloadEnabled($downloadEnabled)
    {
        $this->downloadEnabled = $downloadEnabled;

        return $this;
    }

    /**
     * Get downloadEnabled
     *
     * @return boolean
     */
    public function getDownloadEnabled()
    {
        return $this->downloadEnabled;
    }

    # записывает переданный файл, устанавливает необходимые свойства сущности
    public function processFile($file, $storageDirectory, $oldFilename, $fileType)
    {
        if ($file) {
            # удаляем старый файл с данными
            if ($oldFilename) {
                unlink($storageDirectory . $oldFilename);
            }

            $srcFilename = $file->getClientOriginalName();
            $filename = md5(uniqid()) . '.' . $file->guessExtension();

            # ищем каталог в хранилище с кол-вом файлов в нём < MAX_FILES_CNT_IN_DIR
            $validDir = null;
            # перебираем все подкаталоги в хранилище
            foreach (glob($storageDirectory . '*', GLOB_ONLYDIR) as $dir) {
                $dirFilesCnt = 0;
                # считаем файлы в подкаталогах
                foreach (glob($dir . '/*') as $dirFile) {
                    $dirFilesCnt++;
                }
                if ($dirFilesCnt < self::MAX_FILES_CNT_IN_DIR) {
                    $validDir = preg_replace('/^' . str_replace('/', '\/', $storageDirectory) . '/', '', $dir);
                    break;
                }
            }

            # $storageDirectory - каталог хранилища с завершающим /
            # $validDir - подкаталог с файлами
            # $filename - имя файла

            # если в хранилище нет доступных подкаталогов или все переполнены, создаём новый
            if (!$validDir) {
                $validDir = md5(uniqid());
                mkdir($storageDirectory . $validDir);
            }

            # в имени файла лежит относительный путь от хранилища (с каталогом)
            $file->move($storageDirectory . $validDir, $filename);

            if ($fileType == 'cover') {
                $this->setCoverPath($validDir . '/' . $filename);
                $this->setCoverSrcPath($srcFilename);
            } else {
                $this->setContentPath($validDir . '/' . $filename);
                $this->setContentSrcPath($srcFilename);
            }
        }
    }
}
