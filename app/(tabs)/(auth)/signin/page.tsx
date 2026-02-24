import { useAuthCheck } from "@/app/useAuthCheck/useAuthCheck";
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";
import { Link, useRouter } from "expo-router";
import React from "react";
import { Image, Pressable, SafeAreaView, Text, TextInput, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";
import "../../../../style/global.css";

export default function Login() {
  useAuthCheck();

  const [email, setEmail] = React.useState("");
  const [senha, setSenha] = React.useState("");

  const senhaInputRef = React.useRef<TextInput | null>(null);

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertMessage, setAlertMessage] = React.useState("");
  const [alertTitle, setAlertTitle] = React.useState("Ocorreu um erro");

  // NOVO: modo verificação
  const [alertVariant, setAlertVariant] = React.useState<"error" | "info" | "success">("error");
  const [needVerify, setNeedVerify] = React.useState(false);
  const [verifyToken, setVerifyToken] = React.useState("");
  const [verifying, setVerifying] = React.useState(false);

  const router = useRouter();

  function showError(message: string, title = "Ocorreu um erro") {
    setAlertVariant("error");
    setNeedVerify(false);
    setAlertTitle(title);
    setAlertMessage(message);
    setAlertVisible(true);
  }

  function showVerify(message: string, title = "Verifique seu e-mail") {
    setAlertVariant("info");
    setNeedVerify(true);
    setAlertTitle(title);
    setAlertMessage(message);
    setAlertVisible(true);
  }

async function handleSignIn() {
  if (!email || !senha) {
    showError("Por favor, preencha todos os campos");
    return;
  }

  try {
    const response = await axios.post("http://localhost/SICAD_Oficial/controller/login.php", {
      email,
      senha,
    });

      if (response.data?.success) {
        const token = response.data.token;
        const nome = response.data.usuario.nome;
        const id = response.data.usuario.id;

        await AsyncStorage.setItem("userToken", token);
        await AsyncStorage.setItem("userName", nome);
        await AsyncStorage.setItem("userId", String(id)); // <- string

        router.push("/(tabs)/(painel)/home/page");
        return;
      }

      // AQUI: se não verificado, abre o modal com input
      if (response.data?.code === "EMAIL_NOT_VERIFIED") {
        setVerifyToken("");
        showVerify(response.data?.message ?? "Seu e-mail não foi verificado. Cole o token aqui.");
        return;
      }

      // erro normal
      showError(response.data?.message ?? "Credenciais inválidas");
    } catch (error) {
      console.error("Erro na requisicao: ", error);
      showError("Erro de conexão. Tente novamente.");
    }
  }

  // NOVO: validar token
  async function handleVerifyEmail() {
    const token = verifyToken.trim();
    if (!token) return;

    setVerifying(true);
    try {
      const res = await axios.post("http://localhost/SICAD_Oficial/controller/verify_email.php", {
        email,
        token,
      });

      if (res.data?.success) {
        // fecha modal e tenta login de novo
        setAlertVisible(false);
        setNeedVerify(false);
        setVerifying(false);

        await handleSignIn();
        return;
      }

      // mantém modal aberto, só mostra mensagem de erro
      setAlertVariant("error");
      setNeedVerify(true);
      setAlertTitle("Token inválido");
      setAlertMessage(res.data?.message ?? "Token inválido ou expirado.");
      setAlertVisible(true);
    } catch (e) {
      console.error(e);
      setAlertVariant("error");
      setNeedVerify(true);
      setAlertTitle("Erro de conexão");
      setAlertMessage("Não foi possível validar agora. Tente novamente.");
      setAlertVisible(true);
    } finally {
      setVerifying(false);
    }
  }

  async function handleForgotPassword() {
    if (!email) {
      showError("Digite seu e-mail para enviar o código de redefinição.");
      return;
    }

    try {
      const response = await axios.post("http://localhost/SICAD_Oficial/controller/forgot_password.php", {
        email,
      });

      if (response.data?.success) {
        setAlertVariant("info");
        setNeedVerify(false);
        setAlertTitle("Verifique seu e-mail");
        setAlertMessage(response.data.message);
        setAlertVisible(true);
      } else {
        showError(response.data?.message ?? "Não foi possível enviar o e-mail.");
      }
    } catch (error) {
      console.error("Erro na requisicao: ", error);
      showError("Erro de conexão. Tente novamente.");
    }
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className=" w-5/12">
        <Image
          source={require("../../../../assets/images/side-view-login-cadastro.png")}
          style={{ width: "100%" }}
          className=" mobile:h-0 mobile:w-0 mobile:hidden"
        />
      </View>

      <View className="flex-1 items-center justify-center">
        <View>
          <Image source={require("../../../../assets/images/logo-composta.png")} className="mb-4" />
          <Text className="text-6xl dark:color-white">Acesse sua conta</Text>

          <Text className="text-2xl dark:color-white">
            Ainda não tem uma conta ?{" "}
            <Link href={"/(tabs)/(auth)/signup/page"} className="text-2xl color-sky-500">
              clique aqui para criar uma
            </Link>
          </Text>

          <form action="" className="flex flex-col gap-1 mt-4">
            <View>
              <Text className="text-2xl dark:color-white">E-mail</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    value={email}
                    onChangeText={setEmail}
                    returnKeyType="next"
                    onSubmitEditing={() => senhaInputRef.current?.focus()}
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>

            <View>
              <Text className="text-2xl dark:color-white">Senha</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    secureTextEntry={true}
                    value={senha}
                    onChangeText={setSenha}
                    ref={senhaInputRef}
                    returnKeyType="go"
                    onSubmitEditing={handleSignIn}
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>

            <Pressable
              onPress={handleSignIn}
              className="w-full h-12 rounded-xl bg-green-600 text-2xl font-bold mt-4 color-white justify-center items-center"
            >
              Entrar
            </Pressable>
          </form>

          <Pressable onPress={handleForgotPassword}>
            <Text className="dark:color-white underline text-xl mt-4">Esqueceu sua senha ?</Text>
          </Pressable>

          <Link href="/(tabs)/(painel)/home/page" className="dark:color-white underline text-xl mt-2">
            Já tenho o token / redefinir senha
          </Link>
        </View>
      </View>

      <NiceAlert
        visible={alertVisible}
        title={alertTitle}
        message={alertMessage}
        onClose={() => setAlertVisible(false)}
        variant={alertVariant}
        showInput={needVerify}
        inputPlaceholder="Cole o token aqui"
        inputValue={verifyToken}
        onChangeInput={setVerifyToken}
        confirmText={verifying ? "Validando..." : "Validar"}
        onConfirm={needVerify ? handleVerifyEmail : undefined}
        confirmDisabled={needVerify ? verifying || verifyToken.trim().length === 0 : false}
      />
    </View>
  );
}