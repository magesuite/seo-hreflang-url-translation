<?php

namespace MageSuite\SeoHreflangUrlTranslation\Plugin\MageSuite\SeoHreflang\Block\Hreflang;

class TranslateHreflangUrls
{
    protected $mappedParameterTranslations = [];

    protected $separator = '';

    protected $stores = [];

    /**
     * @var \MageSuite\SeoHreflangUrlTranslation\Model\ResourceModel\FiltrableAttributeOptionValues
     */
    protected $filtrableAttributeOptionValues;
    /**
     * @var \MageSuite\SeoHreflangUrlTranslation\Helper\Configuration
     */
    protected $seoHreflangUrlTranslationConfiguration;
    /**
     * @var \MageSuite\SeoLinkMasking\Service\FiltrableAttributeUtfFriendlyConverter
     */
    protected $utfFriendlyConverter;
    /**
     * @var \MageSuite\SeoLinkMasking\Helper\Configuration
     */
    protected $seoLinkMaskingConfiguration;
    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    protected $storeRepository;

    public function __construct(
        \MageSuite\SeoHreflangUrlTranslation\Model\ResourceModel\FiltrableAttributeOptionValues $filtrableAttributeOptionValues,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \MageSuite\SeoHreflangUrlTranslation\Helper\Configuration $seoHreflangUrlTranslationConfiguration,
        \MageSuite\SeoLinkMasking\Service\FiltrableAttributeUtfFriendlyConverter $utfFriendlyConverter,
        \MageSuite\SeoLinkMasking\Helper\Configuration $seoLinkMaskingConfiguration
    ) {

        $this->filtrableAttributeOptionValues = $filtrableAttributeOptionValues;
        $this->seoHreflangUrlTranslationConfiguration = $seoHreflangUrlTranslationConfiguration;
        $this->utfFriendlyConverter = $utfFriendlyConverter;
        $this->seoLinkMaskingConfiguration = $seoLinkMaskingConfiguration;
        $this->storeRepository = $storeRepository;
    }

    public function afterGetAlternateLinks(\MageSuite\SeoHreflang\Block\Hreflang $subject, $result)
    {
        if ($result && $this->seoHreflangUrlTranslationConfiguration->shouldTranslateHreflangTags()) {
            $this->stores = $this->storeRepository->getList();
            $this->separator = $this->seoLinkMaskingConfiguration->getMultiselectOptionSeparator();
            $result = $this->processHreflangUrls($result);
        }

        return $result;
    }

    protected function processHreflangUrls($hrefLangs)
    {
        $parameters = $this->getUrlParameters($hrefLangs);
        $this->mappedParameterTranslations = $this->getParameterOptionValuesGrouped($parameters);

        foreach ($hrefLangs as $hrefLang) {
            $storeId = null;
            $url = urldecode($hrefLang->getUrl());
            $url = str_replace(' ', '+', $url);

            $urlParsed = parse_url($url);

            $parts = explode('/', $urlParsed['path']);
            $parts = $this->checkMultiOptionsParameters($parts);
            $newParts = [];

            foreach ($parts as $part) {
                if (empty($part)) {
                    $newParts[] = $part;
                    continue;
                }

                if (!is_array($part) && isset($this->stores[$part])) {
                    $currentStore = $this->stores[$part];
                    $storeId = $currentStore->getId();
                }

                if (is_array($part)) {
                    $multiOptionsParamTranslated = [];
                    foreach ($part as $p) {
                        $multiOptionsParamTranslated[] = $this->mapParamsTranslation(strtolower($p), $storeId);
                    }
                    $newParts[] = implode($this->separator, $multiOptionsParamTranslated);
                    continue;
                }

                $newParts[] = $this->mapParamsTranslation($part, $storeId);
            }

            if ($this->seoLinkMaskingConfiguration->isUtfFriendlyModeEnabled()) {
                $newParts = $this->utfFriendlyConverter->convertFilterParams($newParts);
            }

            $newParts = $this->prepareUrlNewParts($newParts);
            $newPath = implode('/', $newParts);
            $urlParsed['path'] = $newPath;
            $newUrl = $urlParsed['scheme'] . '://' . $urlParsed['host'] . $urlParsed['path'];
            $hrefLang->setUrl($newUrl);
        }

        return $hrefLangs;
    }

    protected function getParameterOptionValuesGrouped($parameters)
    {
        $translationsMapped = $this->getParameterOptionValuesFromDb($parameters);
        $groupedTranslations = [];

        foreach ($translationsMapped as $translation) {
            if (count($translation) > 1) {
                foreach ($translation as $storeId => $translated) {
                    $translatedIndex = strtolower($translated);
                    $groupedTranslations[$translatedIndex] = $translation;
                }
            }
        }

        return $groupedTranslations;
    }

    protected function getParameterOptionValuesFromDb($parameters)
    {
        return $this->filtrableAttributeOptionValues->getFiltrableOptionValues($parameters);
    }

    protected function prepareUrlNewParts($params)
    {
        foreach ($params as &$param) {
            $param = strtolower($param);
            $param = str_replace(' ', '+', $param);
        }

        return $params;
    }

    protected function convertSpaceCharacter($param)
    {
        return str_replace('+', ' ', $param);
    }

    protected function mapParamsTranslation($part, $storeId)
    {
        $part = $this->convertSpaceCharacter($part);
        if (!isset($this->mappedParameterTranslations[$part])) {
            return $part;
        }

        if (empty($storeId)) {
            $part = $this->mappedParameterTranslations[$part][\Magento\Store\Model\Store::DEFAULT_STORE_ID];
        }

        if (isset($this->mappedParameterTranslations[$part][$storeId])) {
            $part = $this->mappedParameterTranslations[$part][$storeId];
        } else {
            $part = $this->mappedParameterTranslations[$part][\Magento\Store\Model\Store::DEFAULT_STORE_ID];
        }

        return $part;
    }

    protected function getUrlParameters($hrefLangs)
    {
        $parameters = [];
        foreach ($hrefLangs as $hrefLang) {
            $urlParts = parse_url(urldecode($hrefLang->getUrl()));
            $parameters = explode('/', $urlParts['path']);
            $parameters = $this->checkMultiOptionsParameters($parameters);
        }

        return $parameters;
    }

    protected function checkMultiOptionsParameters($parameters)
    {
        $result = [];
        foreach ($parameters as $param) {
            if (array_key_exists($param, $this->stores)) {
                $result[] = $param;
                continue;
            }
            if (strpos($param, $this->separator)) {
                $newParams = explode($this->separator, $param);
                $result[] = $newParams;
            } else {
                $result[] = $param;
            }
        }
        return $result;
    }
}
