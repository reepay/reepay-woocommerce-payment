'use strict';

let gulp = require('gulp'),
    rename = require('gulp-rename'),
    sass = require('gulp-sass'),
    sourcemaps = require('gulp-sourcemaps'),
    cssmin = require('gulp-minify-css'),
    uglify = require('gulp-uglify-es').default;

gulp.task('css:build', function () {
    return gulp.src('./assets/css/*.scss')
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest('./assets/css'))
        .pipe(cssmin())
        .pipe(rename({
            suffix: '.min',
        }))
        .pipe(sourcemaps.write())
        .pipe(gulp.dest('./assets/css'));
});

gulp.task('css:build:watch', function () {
    gulp.watch('./assets/css/*.scss', gulp.parallel('css:build'));
});

gulp.task('js:build', function () {
    return gulp.src(['./assets/js/*.js', '!./assets/js/*.min.js'])
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(rename(function (path) {
            path.extname = '.min.js';
        }))
        .pipe(sourcemaps.write())
        .pipe(gulp.dest('./assets/js'));
});

gulp.task('js:build:watch', function () {
    gulp.watch('./assets/js/*.js', gulp.parallel('js:build'));
});
