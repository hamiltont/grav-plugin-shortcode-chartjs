# Usage

This page has some new usage examples. 

## Data Flow

Document the order in which data sources are scanned...


## Data Inside Shortcode

To use this approach, you must disable `process.markdown` inside your frontmatter. 
If you do not, then the YAML inside the `chartjs` shortcode will be processed by 
the Markdown formatter, and the plugin will fail to properly process it. 

````
[chartjs]
type: bar
data: 
    labels: [Jan, Feb, Mar, Apr]
    datasets:
      - label: Bars Other Title
        data: [1,2,4,7]
[/chartjs]
````

