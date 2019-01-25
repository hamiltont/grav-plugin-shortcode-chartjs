<?php

namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use \Exception;

function jlog($msg) {
    echo '<script>console.log("' . $msg . '")</script>';
}

class ChartjsShortcode extends Shortcode
{
    private $pluginConfig;
    private $defCanvas;
    private $defWidth;
    private $defHeight;
    private $backgroundcolor;
    private $bordercolor;

    public function init()
    {
        // TODO pull processing into distinct function ...
        $this->shortcode->getHandlers()->add('chartjs', function(ShortcodeInterface $sc) {
            // Get plugin settings
            $this->pluginConfig    = $this->config->get('plugins.shortcode-chartjs');
            $this->defCanvas       = $this->pluginConfig['canvas']['name'];
            $this->defWidth        = $this->pluginConfig['canvas']['width'];
            $this->defHeight       = $this->pluginConfig['canvas']['height'];
            $this->backgroundcolor = $this->pluginConfig['chart']['bkgndcolor'];
            $this->bordercolor     = $this->pluginConfig['chart']['bordercolor'];

            // Get shortcode settings
            /** Example shortcode block
             *
             * [chartjs name="availability" width="100" height="100" type="pie" label="Studio Utilisation"
             * datapoints="55,176" datalabels="Booked, Available" backgroundcolor1="rgba(255, 99, 132, 0.2)"
             * backgroundcolor2="rgba(54, 162, 235, 0.2)" bordercolor1="rgba(255,99,132,1)"
             * bordercolor2="rgba(54, 162, 235, 1)" borderwidth="1"][/chartjs]
             *
             */

            // @todo: Add support for data and config from URL/path

            // Add canvas
            $output = $this->buildCanvas($sc);

            // Add JS libary assets
            $this->shortcode->addAssets('js', '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.6.0/Chart.bundle.min.js');
            
            try {
                // Build chart JS
                // TODO error-fast or fallback to different data sources?
                $id = $sc->getParameter('id', null);
                $contents = $sc->getContent();
                if (isset($contents) && $contents != '') {
                    $chartjs = $this->buildChartWithContents($sc, $contents);
                } elseif (isset($id)) {
                    $chartjs = $this->buildChartWithFrontMatter($sc, $id);
                } else {
                    $chartjs = $this->buildChartWithDatapoints($sc);
                }
                
                $output = $output . "<script>$chartjs</script>";
            } catch (Exception $e) {
                $output = "<p>" . $e->getMessage() . "</p>";
            }

            // Return canvas etc
            return $output;
        });
    }

    private function assembleValues(ShortcodeInterface $sc, $name, $numberOfDataPoints)
    {
        if (is_null($sc) || !is_numeric($numberOfDataPoints) || $numberOfDataPoints == 0)
        {
            return '';
        }

        $defaultValue = $sc->getParameter($name, $this->$name);

        $values = [];

        for ($i = 1; $i <= $numberOfDataPoints; $i++)
        {
            $paramName = $name . $i;
            $color = $sc->getParameter($paramName, $defaultValue);
            $values[] = $color;
        }

        $values = $this->convertArrayToJSRepresentation($values);

        return $values;
    }

    private function convertArrayToJSRepresentation($values)
    {
        if (count($values) > 0)
        {
            $jsStringLiteralArray = '[\'' . implode("','", $values) . '\']';
        } else {
            $jsStringLiteralArray = "[]";
        }

        return $jsStringLiteralArray;
    }

    private function buildChartWithContents($sc, $content)
    {

        # TODO - Fix the 'disable markdown' requirement for using this approach
        # 
        # See https://github.com/getgrav/grav-plugin-shortcode-core/issues/38
        $header = $this->grav['page']->header();
        $header = new \Grav\Common\Page\Header((array) $header);
        $markdown = $header->get('process.markdown');
        var_dump($markdown);
        if (is_null($markdown) || $markdown == 'true' || $markdown == TRUE)
            throw new Exception("Disable markdown processing on this page to manually embed chart data");

        try {
            $content = Yaml::parse($content);
        } catch (ParseException $exception) {
            throw new Exception("Unable to parse YAML - " . $exception->getMessage());
        }

        $data = json_encode($content);
        if (is_null($data) || $data == FALSE)
            throw new Exception("Could not encode chartjs.$id data as JSON");

        $canvasName      = $sc->getParameter('name',   $this->defCanvas);
        return "new Chart(document.getElementById(\"$canvasName\"), $data);";
    }


    private function buildChartWithDatapoints($sc)
    {
        // Chart details
        $type            = $sc->getParameter('type',  'bar');
        $canvasName      = $sc->getParameter('name',   $this->defCanvas);
        $label           = $sc->getParameter('label',  '');
        $dataPoints      = explode(',',$sc->getParameter('datapoints',  ''));
        $dataPointsCount = count($dataPoints);
        $dataPoints      = $this->convertArrayToJSRepresentation($dataPoints);
        $dataLabels      = explode(',',$sc->getParameter('datalabels',  ''));
        $labels          = $this->convertArrayToJSRepresentation($dataLabels);

        // Data point styling
        $bkgndColors  = $this->assembleValues($sc, 'backgroundcolor', $dataPointsCount);
        $borderColors = $this->assembleValues($sc, 'bordercolor', $dataPointsCount);
        $borderWidth  = $sc->getParameter('borderwidth', 1);

        // Chart Options
        $responsive    = $sc->getParameter('responsive', 'true');
        $legend        = $sc->getParameter('legend', 'true');
        $titleDisplay  = $sc->getParameter('titledisplay', 'false');
        $titlePosition = $sc->getParameter('titleposition', 'top');

        // Build our JS from template
        $chartJSBlock = <<< chartjs
var ctx = document.getElementById("$canvasName");
var aChart = new Chart(ctx, {
    type: '$type',
    data: {
        labels: $labels,
        datasets: [{
            label: '$label',
            data: $dataPoints,
            backgroundColor: $bkgndColors,
            borderColor: $borderColors,
            borderWidth: $borderWidth
        }]
    },
    options: {
        title: {
            display: $titleDisplay,
            position: '$titlePosition',
            text: '$label'
        },
        responsive: $responsive,
        legend: $legend,
    }
});
chartjs;

        return $chartJSBlock;
    }

    /**
     * @param $canvasName
     * @param $canvasWidth
     * @param $canvasHeight
     * @param $canvasStyle
     * @return string
     */
    function buildCanvas($sc)
    {
        // Canvas details
        $canvasWidth  = $sc->getParameter('width',  $this->defWidth);
        $canvasHeight = $sc->getParameter('height', $this->defHeight);
        $canvasName   = $sc->getParameter('name',   $this->defCanvas);
        $canvasStyle  = $sc->getParameter('style', false);
        $canvasStyle  = $canvasStyle ? "style=\"$canvasStyle\"" : '';

        return "<canvas id=\"$canvasName\" width=\"$canvasWidth\" height=\"$canvasHeight\" $canvasStyle></canvas>";
    }
}
