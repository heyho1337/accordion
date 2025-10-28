<?php

namespace App\Controller\Admin\Crud;

use App\Entity\AccordionItem;
use App\Entity\Accordion;
use App\Service\Admin\CrudService;
use App\Service\Modules\ImageService;
use App\Service\Modules\LangService;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use App\Service\Modules\TranslateService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Form\FormFactoryInterface;
use App\Trait\Admin\ModalCrud;

class AccordionItemCrudController extends AbstractCrudController
{
    use ModalCrud;

    private string $lang;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private ImageService $imageService,
        private readonly CrudService $crudService,
        private readonly LangService $langService,
        private readonly TranslateService $translateService,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly RouterInterface $router,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory
    ) {
        $this->lang = $this->langService->getDefault();
        if($this->requestStack->getCurrentRequest()){
            $locale = $this->requestStack->getCurrentRequest()->getSession()->get('_locale');
            if($locale){
                $this->lang = $this->requestStack->getCurrentRequest()->getSession()->get('_locale');
                $this->translateService->setLangs($this->lang);
                $this->langService->setLang($this->lang);
            }
        }
    }
    
    public static function getEntityFqcn(): string
    {
        return AccordionItem::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof AccordionItem) return;
        $entityInstance->setOrderNum(0);

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $parentId = $request->query->get('parent');
            if ($parentId !== null) {
                $parent = $entityManager->getRepository(\App\Entity\Accordion::class)->find($parentId);
                if ($parent) {
                    $entityInstance->setParent($parent);
                }
            }
        }

        $this->crudService->setEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof AccordionItem) return;

        $this->crudService->setEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        $this->getContext()->getRequest()->setLocale($this->lang);
        $this->translator->getCatalogue($this->lang);
        $this->translator->setLocale($this->lang);
        /**
         * on forms
         */
        yield FormField::addTab($this->translateService->translateSzavak("options"));
            yield BooleanField::new('active',$this->translateService->translateSzavak("active"))
                ->renderAsSwitch(true)
                ->setFormTypeOptions(['data' => true])
                ->onlyOnForms();

        yield FormField::addTab($this->translateService->translateSzavak($this->langService->getDefaultObject()->getName()));
            yield TextField::new('name_'.$this->langService->getDefault(), $this->translateService->translateSzavak("name"))
                ->hideOnIndex();
            yield Field::new('text_'.$this->langService->getDefault(), $this->translateService->translateSzavak("text"))
                ->setFormType(CKEditorType::class)
                ->onlyOnForms();
        
        foreach($this->langService->getLangs() as $lang){
            if(!$lang->isDefault()){
                yield FormField::addTab($this->translateService->translateSzavak($lang->getName()));
                yield TextField::new('name_'.$lang->getCode(), $this->translateService->translateSzavak("name"))
                    ->hideOnIndex();
                yield Field::new('text_'.$lang->getCode(), $this->translateService->translateSzavak("text"))
                    ->setFormType(CKEditorType::class)
                    ->onlyOnForms();
            }
        }
        
        /**
         * index
         */
        yield TextField::new('name_'.$this->langService->getDefault(), $this->translateService->translateSzavak("name"))
            ->formatValue(function ($value, $entity) {
                $url = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction('edit')
                    ->setEntityId($entity->getId())
                    ->generateUrl();

                return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($value));
            })
            ->onlyOnIndex()
            ->renderAsHtml();
        yield DateField::new('created_at', $this->translateService->translateSzavak("created_at","created"))->hideOnForm();
        yield DateField::new('modified_at',$this->translateService->translateSzavak("modified_at","modified"))->hideOnForm();
        yield BooleanField::new('active', $this->translateService->translateSzavak("active"))
            ->renderAsSwitch(true)
            ->onlyOnIndex();
    }

    public function configureCrud(Crud $crud): Crud
    {

        return $crud
            ->addFormTheme('@FOSCKEditor/Form/ckeditor_widget.html.twig')
            ->addFormTheme('@EasyAdmin/crud/form_theme.html.twig')
            ->overrideTemplates([
                'crud/new' => 'admin/modules/modal_crud_new.html.twig',
            ]);
    }

    public function new(AdminContext $context): KeyValueStore
    {
        $response = parent::new($context); // returns KeyValueStore
        $request = $this->requestStack->getCurrentRequest();
        $id = $request->query->get('parent');


        //$entity = $this->entityManager->getRepository(\App\Entity\Accordion::class)->find($id);

        $url = $this->router->generate('admin_accordion_item_ajax_create', [
            'parent' => $id,
        ]);

        $response = parent::new($context);
        $response->set('url', $url);
        return $response;
    }

    #[Route('/admin/accordion-item/ajax-create', name: 'admin_accordion_item_ajax_create', methods: ['POST'])]
    public function ajaxCreate(Request $request): JsonResponse
    {
        $entity = new AccordionItem();

        $form = $this->buildAccordionItemForm($entity);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entity->setOrderNum(0);

                $parentId = $request->query->get('parent');
                if ($parentId !== null) {
                    $parent = $this->entityManager->getRepository(Accordion::class)->find($parentId);
                    if ($parent) {
                        $entity->setParent($parent);
                    }
                }
                $entity->setCreatedAt(new \DateTimeImmutable());
                $entity->setModifiedAt(new \DateTimeImmutable());

                $this->translateService->localizePersistEntity($entity);

                $this->entityManager->persist($entity);
                $this->entityManager->flush();

                $this->addFlash('success', $this->translateService->translateSzavak("success_upload_accordion","Accordion uploaded successfully"));

                return new JsonResponse(['success' => true, 'id' => $entity->getId()]);
            }

            $errors = $this->getFormErrors($form);
            return new JsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Invalid request',
            'request_data' => $request->request->all(), // POST data
        ], 400);
    }


    private function buildAccordionItemForm(AccordionItem $entity)
    {
        $formBuilder = $this->formFactory->createNamedBuilder('AccordionItem', \Symfony\Component\Form\Extension\Core\Type\FormType::class, $entity, [
            'csrf_protection' => false,
            'method' => 'POST',
            'allow_extra_fields' => true,
        ]);

        $formBuilder->add('active', CheckboxType::class, ['required' => false]);

        foreach ($this->langService->getLangs() as $lang) {
            $code = $lang->getCode();
            $formBuilder->add('name_' . $code, TextType::class, [
                'label' => 'Name ' . $lang->getName(),
                'required' => false,
            ]);
            $formBuilder->add('text_' . $code, CKEditorType::class, [
                'label' => 'Text ' . $lang->getName(),
                'required' => false,
            ]);
        }

        return $formBuilder->getForm();
    }

}
