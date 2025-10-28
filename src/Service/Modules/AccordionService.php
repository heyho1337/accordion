<?php

namespace App\Service\Modules;

use App\Entity\EvcAccordionItems;
use Doctrine\ORM\EntityManagerInterface;

class AccordionService
{
    protected array $accordionItems;
    protected array $galleryItems;

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ){
        $this->accordionItems = $this->entityManager
            ->getRepository(EvcAccordionItems::class)
            ->findAll();
    }

    public function accordionText(string $id): string
    {
        $result = "<div class='accordion' id='{$id}'>";
        foreach ($this->accordionItems as $item) {
            if($item->getAcciAccid() == $id){
                $result .='
				<div class="item">
					<input id="'.$item->getAcciAccid().'" type="checkbox"/>
					<label for="'.$item->getAcciAccid().'"><span>'.$item->getAcciTitle().'</span></label>
					<div class="text">
						'.$item->getAcciLeir().'
					</div>
				</div>';
            }
        }
        $result.="</div>";

        return $result;
    }
}