<?php

namespace Crm\UsersModule\Forms;

use Crm\PrintModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AddressFormFactory
{
    private $userRepository;

    private $countriesRepository;

    private $addressesRepository;

    private $addressTypesRepository;

    private $addressChangeRequestsRepository;

    private $emitter;

    private $translator;

    public $onSave;

    public $onUpdate;

    public function __construct(
        UsersRepository $userRepository,
        AddressesRepository $addressesRepository,
        CountriesRepository $countriesRepository,
        AddressTypesRepository $addressTypesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        Emitter $emitter,
        ITranslator $translator
    ) {
        $this->userRepository = $userRepository;
        $this->addressesRepository = $addressesRepository;
        $this->addressTypesRepository = $addressTypesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->countriesRepository = $countriesRepository;
        $this->emitter = $emitter;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($addressId, $userId)
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);

        $defaults = [];
        $address = $this->addressesRepository->find($addressId);

        if ($addressId) {
            $defaults = $address->toArray();
            if (!$defaults['country_id']) {
                $defaults['country_id'] = $this->countriesRepository->defaultCountry()->id;
            }
            $userId = $address->user_id;
        } else {
            $defaults['country_id'] = $this->countriesRepository->defaultCountry()->id;
            $userRow = $this->userRepository->find($userId);
            $defaults['first_name'] = $userRow->first_name;
            $defaults['last_name'] = $userRow->last_name;
        }

        $type = $form->addSelect('type', 'users.frontend.address.type.label', $this->addressTypesRepository->getPairs());
        if ($addressId) {
            $type->setDisabled(true);
        }

        $form->addText('first_name', 'users.frontend.address.first_name.label')
            ->setAttribute('placeholder', 'users.frontend.address.first_name.placeholder');
        $form->addText('last_name', 'users.frontend.address.last_name.label')
            ->setAttribute('placeholder', 'users.frontend.address.first_name.placeholder');

        $form->addText('phone_number', 'users.frontend.address.phone_number.label')
            ->setAttribute('placeholder', 'users.frontend.address.phone_number.placeholder');
        $form->addText('address', 'users.frontend.address.address.label')
            ->setAttribute('placeholder', 'users.frontend.address.address.placeholder');
        $form->addText('number', 'users.frontend.address.number.label')
            ->setAttribute('placeholder', 'users.frontend.address.number.placeholder');
        $form->addText('zip', 'users.frontend.address.zip.label')
            ->setAttribute('placeholder', 'users.frontend.address.zip.placeholder');
        $form->addText('city', 'users.frontend.address.city.label')
            ->setAttribute('placeholder', 'users.frontend.address.city.placeholder');
        $form->addSelect('country_id', 'users.frontend.address.country.label', $this->countriesRepository->getAllPairs());

        $form->addText('company_name', 'users.frontend.address.company_name.label')
            ->setAttribute('placeholder', 'users.frontend.address.company_name.placeholder');
        $companyId = $form->addText('company_id', 'users.frontend.address.company_id.label')
            ->setAttribute('placeholder', 'users.frontend.address.company_id.placeholder');
        $companyTaxId = $form->addText('company_tax_id', 'users.frontend.address.company_tax_id.label')
            ->setAttribute('placeholder', 'users.frontend.address.company_tax_id.placeholder');
        $companyVatId = $form->addText('company_vat_id', 'users.frontend.address.company_vat_id.label')
            ->setAttribute('placeholder', 'users.frontend.address.company_vat_id.placeholder');

        $companyId->addConditionOn($type, Form::EQUAL, 'invoice')->setRequired('users.frontend.address.company_id.required');
        $companyTaxId->addConditionOn($type, Form::EQUAL, 'invoice')->setRequired('users.frontend.address.company_tax_id.required');
        $companyVatId->addConditionOn($type, Form::EQUAL, 'invoice')->setRequired('users.frontend.address.company_vat_id.required');

        $form->addSubmit('send', 'users.frontend.address.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('users.frontend.address.submit'));

        if ($userId) {
            $form->addHidden('user_id', $userId);
        }
        if ($addressId) {
            $form->addHidden('id', $addressId);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = $this->userRepository->find($values->user_id);
        $address = null;

        if (isset($values->id)) {
            $address = $this->addressesRepository->find($values->id);
        };

        $changeRequest = $this->addressChangeRequestsRepository->add(
            $user,
            $address,
            $values->first_name,
            $values->last_name,
            $values->company_name,
            $values->address,
            $values->number,
            $values->city,
            $values->zip,
            $values->country_id,
            $values->company_id,
            $values->company_tax_id,
            $values->company_vat_id,
            $values->phone_number,
            $values->type ?? null
        );

        if ($changeRequest) {
            $address = $this->addressChangeRequestsRepository->acceptRequest($changeRequest, true);
        }

        if (isset($values->id)) {
            $this->onUpdate->__invoke($form, $address);
        } else {
            $this->onSave->__invoke($form, $address);
        }
    }
}
