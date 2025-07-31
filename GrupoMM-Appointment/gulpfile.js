/** ****************************************************************
                         Configuração do sistema
    **************************************************************** **/

// Carrega os módulos necessários
var
  gulp   = require('gulp-help')(require('gulp'))
  del    = require('del');
  concat = require('gulp-concat'),
  merge  = require('merge-stream'),
  rename = require('gulp-rename'),
  sass   = require('gulp-sass'),
//  uglify = require('gulp-uglify');
  minify = require("gulp-babel-minify");

// As configurações de nossos códigos que rodam no lado cliente
var folders = ['site', 'erp', 'pdf'];
var library = ['formFunctions', 'file.input', 'masked.input', 'spinner.input', 'semantic.requisitions'];

// As configurações de nosso sistema de compilação SASS
var sassExpandedOptions = {
  outputStyle: 'expanded'
}
var sassMinifiedOptions = {
  outputStyle: 'compressed'
}

// As configurações de localização de nossas bibliotecas de terceiros
var
  distAnimate             = 'node_modules/animate.css/*.css';
  distJquery              = 'node_modules/jquery/dist/**/*',
  distJqueryAutocomplete  = 'assets/library/jquery/autocomplete/dist/**/*',
  distSemanticUI          = 'assets/library/semantic-ui/dist/**/*';
  distSemanticUICalendar  = 'node_modules/semantic-ui-calendar/dist/**/*';

// Define o local de destino de nossa compilação
var
  dest           = 'public/assets/';


// =======================================================[ Init ]======
// As tarefas de inicialização da estrutura onde serão colocados os
// arquivos finais.
// ---------------------------------------------------------------------

// Função para limpar o diretório destino globalmente
gulp.task('cleanBase', function () {
  return del([
    dest + '**/*',
    '!' + dest ]);
});

// Função para limpar os códigos Javascript
gulp.task('cleanJS', function () {
  return del([
    dest + 'js/**/*',
    '!' + dest + 'js']);
});

// Função para limpar os códigos CSS
gulp.task('cleanCSS', function () {
  return del([
    dest + 'css/**/*',
    '!' + dest + 'css']);
});

gulp.task('createDestination', function () {
  return gulp
    .src('*.*', {read: false})
    .pipe(gulp.dest(dest));
});


// =======================================================[ Base ]======
// As tarefas de criação dos componentes base fornecidos por terceiros e
// que são utilizados pelo nosso sistema.
// ---------------------------------------------------------------------

// Coloca os arquivos da biblioteca animated CSS
gulp.task('animate', function(){
  return gulp
    .src(distAnimate)
    .pipe(gulp.dest(dest + 'libs/animate/'));
});

// Coloca os arquivos do Datatables
gulp.task('datatables', function(){
  // O pacote base do datatables
  var datatables = gulp.src('node_modules/datatables.net/js/*.js')
    .pipe(gulp.dest(dest + 'libs/datatables/'));
  
  // As modificações de CSS e JS para trabalhar com Semantic UI
  var datatablesSemanticUI_JS = gulp.src('node_modules/datatables.net-se/js/*.js')
    .pipe(gulp.dest(dest + 'libs/datatables/'));
  var datatablesSemanticUI_CSS = gulp.src('node_modules/datatables.net-se/css/*.css')
    .pipe(gulp.dest(dest + 'libs/datatables/'));
  
  // Os plugins do Datatables
  var datatablesPlugins = gulp.src('node_modules/datatables.net-plugins/**/*')
    .pipe(gulp.dest(dest + 'libs/datatables/plugins/'));
  
  // A modificação de internacionalização para o padrão deste site
  var datatablesPlugins_i18n = gulp.src('assets/library/datatables/i18n/**/*')
    .pipe(gulp.dest(dest + 'libs/datatables/plugins/i18n/'));
  
  // A modificação de tratamento de erros
  var datatablesPlugins_handleErrors = gulp.src('assets/library/datatables/handleErrors/**/*')
    .pipe(gulp.dest(dest + 'libs/datatables/plugins/handleErrors/'));

  // Os formatadores de colunas personalizados
  var datatablesPlugins_formatters = gulp.src('assets/library/datatables/formatters/**/*')
    .pipe(gulp.dest(dest + 'libs/datatables/formatters/'));
  var datatablesPlugins_formatters = gulp.src('assets/library/datatables/formatters/**/*')
    .pipe(minify())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(dest + 'libs/datatables/formatters/'));
  
  // O pacote base do plugin de seleção do Datatables
  var datatablesPluginsSelect = gulp.src('node_modules/datatables.net-select/js/*.js')
    .pipe(gulp.dest(dest + 'libs/datatables/plugins/select/'));
  
  // As modificações de CSS e JS do plugin de seleção do Datatables para
  // trabalhar com Semantic UI
  var datatablesPluginsSelectSemanticUI_JS = gulp.src('node_modules/datatables.net-select-se/js/*.js')
    .pipe(gulp.dest(dest + 'libs/datatables/plugins/select/'));
  var datatablesPluginsSelectSemanticUI_CSS = gulp.src('node_modules/datatables.net-select-se/css/*.css')
    .pipe(gulp.dest(dest + 'libs/datatables/plugins/select/'));
  
  return merge( datatables, datatablesSemanticUI_JS,
    datatablesSemanticUI_CSS, datatablesPlugins, datatablesPlugins_i18n,
    datatablesPlugins_handleErrors, datatablesPlugins_formatters,
    datatablesPluginsSelect, datatablesPluginsSelectSemanticUI_JS,
    datatablesPluginsSelectSemanticUI_CSS);
});

// Coloca os arquivos do JQuery
gulp.task('jquery', function(){
  return gulp
    .src(distJquery)
    .pipe(gulp.dest(dest + 'libs/jquery/'));
});
gulp.task('autocomplete', function(){
  return gulp
    .src(distJqueryAutocomplete)
    .pipe(gulp.dest(dest + 'libs/jquery/plugins/autocomplete/'));
});

// Coloca os arquivos do Semantic UI
gulp.task('semantic-ui', function(){
  return gulp
    .src(distSemanticUI)
    .pipe(gulp.dest(dest + 'libs/semantic-ui/'));
});

// Coloca os arquivos do Semantic UI Calendar
gulp.task('semantic-ui-calendar', function(){
  return gulp
    .src(distSemanticUICalendar)
    .pipe(gulp.dest(dest + 'libs/semantic-ui-calendar/'));
});


// ================================================[ Javascripts ]======
// As tarefas de criação dos nossos códigos Javascript.
// ---------------------------------------------------------------------

gulp.task('jsExpanded', function() {
  var tasks = folders.map(function(element){
    return gulp.src('assets/js/' + element + '/*.js')
      .pipe(concat(element + '.js'))
      .pipe(gulp.dest(dest + 'js/'));
  });
  
  return merge(tasks);
});
gulp.task('jsMinified', function() {
  var tasks = folders.map(function(element){
    return gulp.src('assets/js/' + element + '/*.js')
      .pipe(concat(element + '.min.js'))
      .pipe(minify({
        mangle: {
          keepClassName: true
        }
      }))
      .pipe(gulp.dest(dest + 'js/'));
  });
  
  return merge(tasks);
});


// ====================================================[ Library ]======
// As tarefas de criação de nossas bibliotecas.
// ---------------------------------------------------------------------

gulp.task('libExpandedJS', function() {
  return gulp.src('assets/library/*.js')
    .pipe(gulp.dest(dest + 'js/'));
});
gulp.task('libMinifiedJS', function() {
  return gulp.src('assets/library/*.js')
    .pipe(minify({
      mangle: {
        keepClassName: true
      }
    }))
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(dest + 'js/'));
});

gulp.task('libExpandedCSS', function () {
  return gulp.src('assets/library/*.scss')
    .pipe(sass(sassExpandedOptions).on('error', sass.logError))
    .pipe(gulp.dest(dest + 'css/'));
});
gulp.task('libMinifiedCSS', function () {
  return gulp.src('assets/library/*.scss')
    .pipe(sass(sassMinifiedOptions).on('error', sass.logError))
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(dest + 'css/'));
});


// ========================================================[ CSS ]======
// As tarefas de criação dos nossos CSS.
// ---------------------------------------------------------------------

gulp.task('cssExpanded', function () {
  var tasks = folders.map(function(element){
    return gulp.src('assets/scss/' + element + '/*.scss')
      .pipe(sass(sassExpandedOptions).on('error', sass.logError))
      .pipe(concat(element + '.css'))
      .pipe(gulp.dest(dest + 'css/'));
  });

  return merge(tasks);
});
gulp.task('cssMinified', function () {
  var tasks = folders.map(function(element){
    return gulp.src('assets/scss/' + element + '/*.scss')
      .pipe(sass(sassMinifiedOptions).on('error', sass.logError))
      .pipe(concat(element + '.min.css'))
      .pipe(gulp.dest(dest + 'css/'));
  });

  return merge(tasks);
});


// ======================================================[ Tasks ]======
// As tarefas disponibilizadas
// ---------------------------------------------------------------------

// Os conjuntos de tarefas bases
gulp.task('buildComponents', ['animate', 'datatables', 'jquery', 'autocomplete', 'semantic-ui', 'semantic-ui-calendar']);
gulp.task('buildJS', ['jsExpanded', 'jsMinified']);
gulp.task('buildLibs', ['libExpandedJS', 'libMinifiedJS', 'libExpandedCSS', 'libMinifiedCSS']);
gulp.task('buildCSS', ['cssExpanded', 'cssMinified']);

// Cria as tarefas disponibilizadas
gulp.task('clean', ['cleanBase']);
gulp.task('default', ['cleanBase', 'buildComponents', 'buildJS', 'buildLibs', 'buildCSS']);
gulp.task('build', ['cleanJS', 'cleanCSS', 'buildJS', 'buildLibs', 'buildCSS']);
