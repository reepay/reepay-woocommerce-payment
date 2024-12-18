'use strict'

const gulp = require('gulp'),
        rename = require('gulp-rename'),
        gulpif = require('gulp-if'),
        sass = require('gulp-sass')(require('sass')),
        cssmin = require('gulp-clean-css'),
        uglify = require('gulp-uglify-es').default,
        argv = require('yargs').argv,
        fs = require('fs-extra'),
        postcss = require('gulp-postcss');

const config = {
    sourceMaps: !argv.production // --production
}

gulp.task(
        'css:build',
        async function () {
            return gulp.src('./assets/css/*.scss')
                    .pipe(gulpif(config.sourceMaps, postcss([], { map: { inline: false } })))
                    .pipe(sass().on('error', sass.logError))
                    .pipe(gulp.dest('./assets/dist/css'))
                    .pipe(cssmin())
                    .pipe(
                            rename(
                                    {
                                        suffix: '.min',
                                    }
                            )
                    )
                    .pipe(gulp.dest('./assets/dist/css'))

        }
)

gulp.task(
        'css:build:watch',
        function () {
            gulp.watch('./assets/css/*.scss', gulp.parallel('css:build'))
        }
)

gulp.task(
        'js:build',
        async function () {
            return gulp.src(['./assets/js/*.js'])
                    .pipe(gulp.dest('./assets/dist/js'))
                    .pipe(uglify())
                    .pipe(
                            rename(
                                    function (path) {
                                        path.extname = '.min.js'
                                    }
                            )
                    )
                    .pipe(gulp.dest('./assets/dist/js'))
        }
)

gulp.task(
        'js:build:watch',
        function () {
            gulp.watch('./assets/js/*.js', gulp.parallel('js:build'))
        }
)

gulp.task(
        'watch',
        gulp.parallel('js:build:watch', 'css:build:watch')
)

gulp.task(
        'clean',
        async function () {
            fs.removeSync('./assets/dist')
        }
)

gulp.task(
        'build',
        gulp.series('clean', 'css:build', 'js:build')
)