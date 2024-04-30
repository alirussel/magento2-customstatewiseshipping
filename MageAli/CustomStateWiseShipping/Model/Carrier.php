<?php
namespace MageAli\CustomStateWiseShipping\Model;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Helper\Carrier as ShippingCarrierHelper;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use MageAli\CustomStateWiseShipping\Helper\Data;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'customstateshippingrate';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     *
     * @var MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * Carrier helper
     *
     * @var ShippingCarrierHelper
     */
    protected $_carrierHelper;

    /**
     * @var CollectionFactory
     */
    protected $_rateFactory;

    /**
     * @var State
     */
    protected $_state;

    /**
     * @var Data
     */
    protected $_customShippingRateHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateFactory
     * @param ShippingCarrierHelper $carrierHelper
     * @param MethodFactory $rateMethodFactory
     * @param State $state
     * @param Data $customShippingRateHelper
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateFactory,
        ShippingCarrierHelper $carrierHelper,
        MethodFactory $rateMethodFactory,
        State $state,
        Data $customShippingRateHelper,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->_scopeConfig = $scopeConfig;
        $this->_rateErrorFactory = $rateErrorFactory;
        $this->_logger = $logger;
        $this->_rateFactory = $rateFactory;
        $this->_carrierHelper = $carrierHelper;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_state = $state;
        $this->_customShippingRateHelper = $customShippingRateHelper;
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return Collection|Result
     * @throws LocalizedException
     */
    public function collectRates(RateRequest $request)
    {
        $result = $this->_rateFactory->create();

        if (!$this->getConfigFlag('active') ) {
            return $result;
        }
        
        $freeBoxes = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $freeBoxes += $item->getQty() * $child->getQty();
                        }
                    }
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                }
            }
        }
        $this->setFreeBoxes($freeBoxes);

        

        
        
        
        

        //$destinationRegion = $request->getDestCountryId();
        //$destinationRegion = $request->getDestRegionId();
        $destinationRegion = $request->getDestRegionCode();
        $isShippingFound = 0;
        foreach ($this->_customShippingRateHelper->getShippingType($request->getStoreId()) as $shippingType) {
            if($shippingType['code'] === $destinationRegion ){
                
                if ($this->getConfigData('type') == 'O') {
                    // per order
                    $shippingPrice = $shippingType['price'];
                } elseif ($this->getConfigData('type') == 'I') {
                    // per item
                    $shippingPrice = $request->getPackageQty() * $shippingType['price'] - $this->getFreeBoxes() * $shippingType['price'];
                } else {
                    $shippingPrice = false;
                }
                $shippingPrice = $this->getFinalPriceWithHandlingFee($shippingPrice);
                
                if ($shippingPrice !== false) {
                    $rate = $this->_rateMethodFactory->create();
                    $rate->setCarrier($this->_code);
                    $rate->setCarrierTitle($this->getConfigData('title'));
                    $rate->setMethod($shippingType['code']);
                    //$rate->setMethodTitle($shippingType['title']);
                    $rate->setMethodTitle($destinationRegion);
                    $rate->setCost($shippingPrice);
                    $rate->setPrice($shippingPrice);
                    $result->append($rate);
                    
                    $isShippingFound = 1;
                }
            }
        }
        
        if($isShippingFound==0){
            
            if ($this->getConfigData('type') == 'O') {
                // per order
                $shippingPrice = $this->getConfigData('price');
            } elseif ($this->getConfigData('type') == 'I') {
                // per item
                $shippingPrice = $request->getPackageQty() * $this->getConfigData('price') - $this->getFreeBoxes() * $this->getConfigData('price');
            } else {
                $shippingPrice = false;
            }
            $shippingPrice = $this->getFinalPriceWithHandlingFee($shippingPrice);
            
            if ($shippingPrice !== false) {
                $method = $this->_rateMethodFactory->create();
                $method->setCarrier($this->_code);
                $method->setCarrierTitle($this->getConfigData('title'));
                $method->setMethod($this->getConfigData('name'));
                $method->setMethodTitle($this->getConfigData('name'));
                $method->setPrice($shippingPrice);
                $method->setCost($shippingPrice);
    
                $result->append($method);   
            }
        }

        return $result;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        //$result = [];
        /*foreach ($this->_customShippingRateHelper->getShippingType() as $shippingType) {
            $result[$shippingType['code']] = $shippingType['title'];
            
        }*/
        return ['customstateshippingrate' => $this->getConfigData('name')];
        //return $result;
    }

    
}
