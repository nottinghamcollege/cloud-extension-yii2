/** global: Craft */
/** global: Garnish */

Craft.CloudUploader = Craft.Uploader.extend(
  {
    init: function ($element, settings) {
      settings = $.extend({}, Craft.CloudUploader.defaults, settings);
      this.base($element, settings);

      // TODO: toggle this based on a new setting (settings.replace)
      this.url = settings.paramName === 'replaceFile'
        ? Craft.getActionUrl('cloud/replace-asset')
        : Craft.getActionUrl('cloud/create-asset');
      this.uploader.off('fileuploadadd');
      this.formData = {};
      this.$dropZone = settings.dropZone
      this.$fileInput = settings.fileInput;
      this.$fileInput.on('change', (event) => this.uploadFiles.call(this, event.target.files));
      this.resetCounters();

      if (this.allowedKinds && !this._extensionList) {
        this._createExtensionList();
      }

      if (this.$dropZone) {
        this.$dropZone.on({
          dragover: (event) => {
            if(this.handleDragEvent(event)) {
              event.dataTransfer.dropEffect = 'copy';
            }
          },
          drop: (event) => {
            if (this.handleDragEvent(event)) {
              this.uploadFiles(event.dataTransfer.files);
            }
          },
          dragenter: this.handleDragEvent,
          dragleave: this.handleDragEvent,
        });
      }
    },

    handleDragEvent: function(event) {
      if (!event?.dataTransfer?.files) {
        return false;
      }

      event.preventDefault();
      event.stopPropagation();

      return true;
    },

    /**
     * Set uploader parameters.
     */
    setParams: function (paramObject) {
      // If CSRF protection isn't enabled, these won't be defined.
      if (Craft?.csrfTokenName && Craft?.csrfTokenValue) {
        paramObject[Craft.csrfTokenName] = Craft.csrfTokenValue;
      }

      this.formData = paramObject;
    },
    uploadFiles: async function (FileList) {
      const files = [...FileList];
      const validFiles = files.filter((file) => {
        let valid = true;

        if (this._extensionList?.length) {
          const matches = file.name.match(/\.([a-z0-4_]+)$/i);
          const fileExtension = matches[1];

          if (this._extensionList.includes(fileExtension.toLowerCase())) {
            this._rejectedFiles.type.push('“' + file.name + '”');
            valid = false;
          }
        }

        if (file.size > this.settings.maxFileSize) {
          this._rejectedFiles.size.push('“' + file.name + '”');
          valid = false;
        }

        if (
          valid &&
          typeof this.settings.canAddMoreFiles === 'function' &&
          !this.settings.canAddMoreFiles(this._validFileCounter)
        ) {
          this._rejectedFiles.limit.push('“' + file.name + '”');
          valid = false;
        }

        if (valid) {
          this._totalBytes += file.size;
          this._validFileCounter++;
          this._inProgressCounter++;
        }

        return valid;
      });

      this.processErrorMessages();

      this.$element.trigger('fileuploadstart');

      for (const file of validFiles) {
        await this.uploadFile(file);
        this._inProgressCounter--;
      }

      this.resetCounters();
    },
    uploadFile: async function (file) {
      const formData = Object.assign({}, this.formData, {
        filename: file.name,
        lastModified: file.lastModified,
      });

      try {
        let response = await Craft.sendActionRequest(
          'POST',
          'cloud/get-upload-url',
          {
            data: formData,
          }
        );

        Object.assign(formData, response.data, {
          size: file.size,
        });

        try {
          const {width, height} = await this.getImage(file);
          Object.assign(formData, {width, height});
        } catch (e) {}

        await axios.put(response.data.url, file, {
          headers: {
            'Content-Type': file.type,
          },
          onUploadProgress: (axiosProgressEvent) => {
            this._uploadedBytes = this._uploadedBytes + axiosProgressEvent.loaded - this._lastUploadedBytes;
            this._lastUploadedBytes = axiosProgressEvent.loaded;
            this.$element.trigger('fileuploadprogressall', {
              loaded: this._uploadedBytes,
              total: this._totalBytes,
            });
          },
        });

        response = await axios.post(this.url, formData);
        this.$element.trigger('fileuploaddone', response.data);
      } catch (error) {
        this.$element.trigger('fileuploadfail', {
          message: error.message,
          filename: file.name,
        });
      } finally {
        this._lastUploadedBytes = 0;
        this.$element.trigger('fileuploadalways');
      }
    },

    resetCounters: function () {
      this._totalBytes = 0;
      this._uploadedBytes = 0;
      this._lastUploadedBytes = 0;
      this._inProgressCounter = 0;
    },

    /**
     * Get the number of uploads in progress.
     */
    getInProgress: function () {
      return this._inProgressCounter;
    },

    getImage: function(file) {
      return new Promise((resolve, reject) => {
        if (!file.type.startsWith('image/')) {
          reject(new Error('File is not an image.'));
        }

        var reader = new FileReader();

        reader.addEventListener('load', (event) => {
          const image = new Image();
          image.src = reader.result;

          image.addEventListener('load', (event) => {
            resolve(event.target)
          });

          image.addEventListener('error', (event) => {
            reject(new Error('Error loading image.'));
          });
        }, false);

        reader.readAsDataURL(file);
      });
    }
  },
  {
    defaults: {
      maxFileSize: Craft.maxUploadFileSize,
    },
  }
);

// Register it!
Craft.registerAssetUploaderClass('craft\\cloud\\fs\\AssetFs', Craft.CloudUploader);
