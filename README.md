
# CSVReader
Ease to use CSV Reader for PHP 7+

## Instalation
In your project root, run the following composer command:

    $ composer require tonyfarney/csv-reader

## General orientations
CSVReader loads CSV from string and returns an array containing all lines read. Each position is a line e each line is an array of columns.
For all the examples of use, we will consider the following code as the base implementation:

    <?php
    require __DIR__.'/vendor/autoload.php';
        
    use \CSVReader\CSVReader;
        
    $csvWithHeader = <<<CSV
    name,role,age
    "Tony Farney","Developer",30
    "The Coffe Guy","Intern",18
    "Someone Else","Developer",26
    CSV;
        
    $csvWithoutHeader = <<<CSV
    "Tony Farney","Developer",30
    "The Coffe Guy","Intern",18
    "Someone Else","Developer",26
    CSV;
        
    $reader = new CSVReader();

## Loading CSV with a header line
    // Reads all the three columns
    $lines = $reader->setHeader(['name', 'role', 'age'])->load($csvWithHeader);
    
    // The colmuns order in the header definition doesn't matter
    $lines = $reader->setHeader(['role', 'name', 'age'])->load($csvWithHeader); // Works exactly like the previous line
    
    // Columns in CSV that are not in definition causes error
    $lines = $reader->setHeader(['role', 'name'])->load($csvWithHeader); // CSVReader\CSVReaderException: The following columns are not expected in the header: age
    
    // Columns in definition that are not in CSV doesn't causes error. That allows the "optional columns" behavior
    $lines = $reader->setHeader(['role', 'name', 'age', 'another_column'])->load($csvWithHeader); // Works perfectly

All the succesfull reads above will output something like:

    array(3) {
      [0]=>
      array(3) {
        ["name"]=>
        string(11) "Tony Farney"
        ["role"]=>
        string(9) "Developer"
        ["age"]=>
        string(2) "30"
      }
      [1]=>
      array(3) {
        ["name"]=>
        string(13) "The Coffe Guy"
        ["role"]=>
        string(6) "Intern"
        ["age"]=>
        string(2) "18"
      }
      [2]=>
      array(3) {
        ["name"]=>
        string(12) "Someone Else"
        ["role"]=>
        string(9) "Developer"
        ["age"]=>
        string(2) "26"
      }
    }
    
 ## Loading CSV without a header line
    // Sets the indexes for the columns array, outputing exact the same as the header examples
    $lines = $reader->setIndexing(['name', 'role', 'age'])->load($csvWithoutHeader);
    
The number of indexes definition must match with the read columns from CSV

    $lines = $reader->setIndexing(['name', 'role', 'age', 'another_column'])->load($csvWithoutHeader);
    $lines = $reader->setIndexing(['name', 'role')->load($csvWithoutHeader);

Both lines above will throw the following error:

    CSVReader\CSVReaderException: The following lines of the CSV has mismatching number of values: 2, 3, 4

It's possible read CSV without have an indexed array of columns:

    $lines = $reader->load($csvWithoutHeader);

Output:

    array(3) {
      [0]=>
      array(3) {
        [0]=>
        string(11) "Tony Farney"
        [1]=>
        string(9) "Developer"
        [2]=>
        string(2) "30"
      }
      [1]=>
      array(3) {
        [0]=>
        string(13) "The Coffe Guy"
        [1]=>
        string(6) "Intern"
        [2]=>
        string(2) "18"
      }
      [2]=>
      array(3) {
        [0]=>
        string(12) "Someone Else"
        [1]=>
        string(9) "Developer"
        [2]=>
        string(2) "26"
      }
    }

## Tips
When reusing the same reader instance for read CSV with different configurations, it's necessary do a reset to avoid errors:

    // First load
    $lines = $reader->setHeader(['name', 'role', 'age'])->load($csvWithHeader);
    // Second load requires a reset
    $lines = $reader->reset()->load($csvWithoutHeader);

The reader will try to detect automatically the column delimiter by reading the first line and determining the usual delimiter (",", ";", "|", "\t") with most occurences, but it's possible manually set what delimiter to use:

    $reader->setColumnDelimiter(';');
    
It's possible to set the enclosure (defaults to ") and the escape (defaults to \) characters :

    $reader->setEnclosure('"')->setScape('\\');
    
## Contributions/Support
You are welcome to contribute with improvement, bug fixes, new ideas, etc.  Any doubt/problem, please contact me by email: tonyfarney@gmail.com. I'll be glad to help you ;)

